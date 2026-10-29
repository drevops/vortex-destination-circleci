<?php

namespace Drupal\Tests\shield\Kernel;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\shield\ShieldMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests when Shield's middleware loads the module files.
 *
 * The middleware runs before Drupal loads its module files. Anything that
 * fires a hook from there caches an incomplete implementation list, so Shield
 * has to load the module files first, but only for the requests that really
 * need them: doing it unconditionally defeats the page cache, which Shield
 * deliberately runs in front of.
 *
 * @group shield
 *
 * @see https://www.drupal.org/project/shield/issues/3277210
 *
 * @coversDefaultClass \Drupal\shield\ShieldMiddleware
 */
class ShieldMiddlewareBootstrapTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'basic_auth',
    'key',
    'path_alias',
    'shield',
    'system',
    'user',
  ];

  /**
   * The mocked module handler, used to observe loadAll() calls.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['shield']);
    // The tests run under the CLI SAPI, which Shield bypasses by default.
    $this->config('shield.settings')
      ->set('shield_enable', TRUE)
      ->set('allow_cli', FALSE)
      ->save();
  }

  /**
   * Builds the middleware with a module handler that counts loadAll() calls.
   *
   * @param bool $basic_auth_installed
   *   Whether the module handler should report basic_auth as installed.
   *
   * @return \Drupal\shield\ShieldMiddleware
   *   The middleware to test.
   */
  protected function buildMiddleware(bool $basic_auth_installed = FALSE): ShieldMiddleware {
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->moduleHandler->method('moduleExists')
      ->willReturnCallback(static fn (string $module) => $module === 'basic_auth' && $basic_auth_installed);

    $kernel = $this->createMock(HttpKernelInterface::class);
    $kernel->method('handle')->willReturn(new Response('passed through'));

    return new ShieldMiddleware(
      $kernel,
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->moduleHandler,
      $this->container->get('request_stack'),
      $this->container->get('logger.channel.shield'),
      $this->container->get('path_alias.repository')
    );
  }

  /**
   * Tests that an allowed path bypasses Shield without loading the modules.
   *
   * @covers ::handle
   * @covers ::checkPathAllowed
   */
  public function testAllowedPathDoesNotLoadModules(): void {
    $this->config('shield.settings')
      ->set('method', ShieldMiddleware::EXCLUDE_METHOD)
      ->set('paths', '/api/status')
      ->save();

    $middleware = $this->buildMiddleware();
    $this->moduleHandler->expects($this->never())->method('loadAll');

    $response = $middleware->handle(Request::create('/api/status'));
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests that the built in credential provider does not load the modules.
   *
   * @covers ::handle
   */
  public function testShieldProviderDoesNotLoadModules(): void {
    $this->config('shield.settings')
      ->set('credential_provider', 'shield')
      ->set('credentials.shield.user', 'user')
      ->set('credentials.shield.pass', 'pass')
      ->save();

    $middleware = $this->buildMiddleware();
    $this->moduleHandler->expects($this->never())->method('loadAll');

    $response = $middleware->handle(Request::create('/protected'));
    $this->assertSame(401, $response->getStatusCode());
  }

  /**
   * Tests that the key credential provider loads the modules first.
   *
   * @covers ::handle
   */
  public function testKeyProviderLoadsModules(): void {
    $this->config('shield.settings')
      ->set('credential_provider', 'key')
      ->set('credentials.key.user', 'user')
      ->set('credentials.key.pass_key', 'missing_key')
      ->save();

    $middleware = $this->buildMiddleware();
    $this->moduleHandler->expects($this->once())->method('loadAll');

    $response = $middleware->handle(Request::create('/protected'));
    $this->assertSame(401, $response->getStatusCode());
  }

  /**
   * Tests that domain matching does not use the path matcher.
   *
   * @covers ::handle
   */
  public function testAllowedDomainBypassesShield(): void {
    $this->config('shield.settings')
      ->set('domains', "example.com\n*.example.org")
      ->save();

    $middleware = $this->buildMiddleware();
    $this->moduleHandler->expects($this->never())->method('loadAll');

    $request = Request::create('http://staging.example.org/protected');
    $response = $middleware->handle($request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('skipped (domain)', $request->headers->get('X-Shield-Status'));
  }

  /**
   * Tests that a request without credentials never loads the modules.
   *
   * When basic_auth is installed and the headers are configured to be unset,
   * which is the default, bypass() checks every request against it. A request
   * that carries no credentials cannot authenticate, so nothing must be loaded
   * for it: those are the requests the page cache serves, and Shield runs in
   * front of the page cache precisely so that it does not add a full bootstrap
   * to them.
   *
   * @covers ::bypass
   *
   * @dataProvider providerRequestsWithoutCredentials
   */
  public function testNoCredentialsDoesNotLoadModules(array $settings, string $status): void {
    $this->installEntitySchema('user');
    $config = $this->config('shield.settings');
    foreach ($settings as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();

    $middleware = $this->buildMiddleware(TRUE);
    $this->moduleHandler->expects($this->never())->method('loadAll');

    $request = Request::create('/api/status');
    $response = $middleware->handle($request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame($status, $request->headers->get('X-Shield-Status'));
  }

  /**
   * Data provider for ::testNoCredentialsDoesNotLoadModules().
   *
   * @return array[]
   *   Test cases, each holding shield.settings overrides and the expected
   *   X-Shield-Status header.
   */
  public static function providerRequestsWithoutCredentials(): array {
    return [
      'shield disabled' => [
        ['shield_enable' => FALSE],
        'disabled',
      ],
      'allowed path' => [
        ['method' => ShieldMiddleware::EXCLUDE_METHOD, 'paths' => '/api/status'],
        'skipped (path)',
      ],
      'basic_auth bypass enabled' => [
        [
          'method' => ShieldMiddleware::EXCLUDE_METHOD,
          'paths' => '/api/status',
          'basic_auth_bypass' => TRUE,
        ],
        'skipped (path)',
      ],
    ];
  }

  /**
   * Tests that the basic_auth bypass loads the modules before authenticating.
   *
   * Verifying a Drupal account loads a user entity, which runs entity type
   * discovery, so the module files have to be loaded first.
   *
   * @covers ::handle
   */
  public function testBasicAuthBypassLoadsModules(): void {
    $this->installEntitySchema('user');
    $this->config('shield.settings')
      ->set('basic_auth_bypass', TRUE)
      ->save();

    $middleware = $this->buildMiddleware(TRUE);
    $this->moduleHandler->expects($this->once())->method('loadAll');

    $request = Request::create('/protected', 'GET', [], [], [], [
      'PHP_AUTH_USER' => 'nobody',
      'PHP_AUTH_PW' => 'nothing',
    ]);
    $response = $middleware->handle($request);
    $this->assertSame(401, $response->getStatusCode());
  }

}
