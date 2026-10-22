<?php

namespace Drupal\Tests\shield\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\shield\ShieldMiddleware;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests that repeated failed authentications are rate limited.
 *
 * @coversDefaultClass \Drupal\shield\ShieldMiddleware
 *
 * @group shield
 */
#[RunTestsInSeparateProcesses]
class ShieldFloodControlTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'path_alias',
    'shield',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['shield']);
    $this->config('shield.settings')
      ->set('shield_enable', TRUE)
      ->set('allow_cli', FALSE)
      ->set('debug_header', TRUE)
      ->set('credential_provider', 'shield')
      ->set('credentials.shield.user', 'user')
      ->set('credentials.shield.pass', 'password')
      ->save();

    // Shield reuses core's user login flood settings.
    $this->config('user.flood')
      ->set('ip_limit', 2)
      ->set('ip_window', 3600)
      ->save();
  }

  /**
   * Flood control is enabled by default on a fresh install.
   */
  public function testEnabledByDefault() {
    $this->assertTrue($this->config('shield.settings')->get('flood_control_enabled'));
  }

  /**
   * The flood service is injected into the middleware service.
   */
  public function testServiceWiring() {
    $middleware = $this->container->get('shield.middleware');
    $property = new \ReflectionProperty(ShieldMiddleware::class, 'flood');
    $this->assertSame($this->container->get('flood'), $property->getValue($middleware));
  }

  /**
   * An IP that reaches the limit is blocked, valid credentials included.
   *
   * @covers ::handle
   * @covers ::floodBlockedResponse
   */
  public function testBlockedAfterLimit() {
    for ($i = 0; $i < 2; $i++) {
      $response = $this->handleRequest('user', 'wrong');
      $this->assertSame(401, $response->getStatusCode());
      $this->assertSame('pending', $response->headers->get('X-Shield-Status'));
    }

    $response = $this->handleRequest('user', 'wrong');
    $this->assertSame(429, $response->getStatusCode());
    $this->assertSame('blocked (flood)', $response->headers->get('X-Shield-Status'));
    $this->assertSame('3600', $response->headers->get('Retry-After'));
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('Access temporarily blocked', $response->getContent());

    // A blocked IP must not get its credentials evaluated at all, otherwise
    // the brute force could simply continue past the limit.
    $response = $this->handleRequest('user', 'password');
    $this->assertSame(429, $response->getStatusCode());
  }

  /**
   * A request without credentials is prompted, never blocked.
   *
   * @covers ::handle
   */
  public function testNoCredentialsNotRateLimited() {
    for ($i = 0; $i < 5; $i++) {
      $response = $this->handleRequest();
      $this->assertSame(401, $response->getStatusCode());
      $this->assertSame('pending', $response->headers->get('X-Shield-Status'));
    }
    // Nothing was recorded either.
    $this->assertTrue($this->container->get('flood')->isAllowed(ShieldMiddleware::FLOOD_EVENT, 1, 3600, '127.0.0.1'));
  }

  /**
   * A successful authentication clears the recorded failures.
   *
   * @covers ::handle
   */
  public function testSuccessClearsFailures() {
    $this->assertSame(401, $this->handleRequest('user', 'wrong')->getStatusCode());
    $this->assertSame(200, $this->handleRequest('user', 'password')->getStatusCode());

    // Without the clear the second failure below would be the one that
    // reaches the limit.
    for ($i = 0; $i < 2; $i++) {
      $this->assertSame(401, $this->handleRequest('user', 'wrong')->getStatusCode());
    }
    $this->assertSame(429, $this->handleRequest('user', 'wrong')->getStatusCode());
  }

  /**
   * Each client IP has its own limit.
   *
   * @covers ::handle
   */
  public function testLimitIsPerIp() {
    for ($i = 0; $i < 2; $i++) {
      $this->handleRequest('user', 'wrong', '10.0.0.1');
    }
    $this->assertSame(429, $this->handleRequest('user', 'wrong', '10.0.0.1')->getStatusCode());
    $this->assertSame(401, $this->handleRequest('user', 'wrong', '10.0.0.2')->getStatusCode());
    $this->assertSame(200, $this->handleRequest('user', 'password', '10.0.0.2')->getStatusCode());
  }

  /**
   * A site that has not run shield_update_8014() yet is rate limited.
   *
   * 8.x-1.9 added flood control without an update hook, so a site upgrading
   * from it, or from 8.x-1.8, has no value stored until updates run.
   *
   * @covers ::handle
   * @covers ::isFloodControlEnabled
   */
  public function testMissingSettingIsEnabled() {
    $this->config('shield.settings')->clear('flood_control_enabled')->save();
    $this->assertNull($this->config('shield.settings')->get('flood_control_enabled'));

    for ($i = 0; $i < 2; $i++) {
      $this->assertSame(401, $this->handleRequest('user', 'wrong')->getStatusCode());
    }
    $this->assertSame(429, $this->handleRequest('user', 'wrong')->getStatusCode());
  }

  /**
   * The setting turns the rate limiting off.
   *
   * @covers ::handle
   */
  public function testDisabled() {
    $this->config('shield.settings')->set('flood_control_enabled', FALSE)->save();

    for ($i = 0; $i < 5; $i++) {
      $response = $this->handleRequest('user', 'wrong');
      $this->assertSame(401, $response->getStatusCode());
      $this->assertSame('pending', $response->headers->get('X-Shield-Status'));
    }
  }

  /**
   * Passes a request to the middleware.
   *
   * @param string|null $user
   *   The user name to send, or NULL to send no credentials.
   * @param string|null $pass
   *   The password to send.
   * @param string $ip
   *   The client IP address the request comes from.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response of the middleware.
   */
  protected function handleRequest($user = NULL, $pass = NULL, $ip = '127.0.0.1') {
    $http_kernel = $this->createMock(HttpKernelInterface::class);
    $http_kernel->expects($this->any())
      ->method('handle')
      ->willReturn(new Response('Passed the shield.'));

    $middleware = new ShieldMiddleware(
      $http_kernel,
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
      $this->container->get('request_stack'),
      $this->container->get('logger.channel.shield'),
      $this->container->get('path_alias.repository')
    );
    $middleware->setFlood($this->container->get('flood'));

    $server = ['REMOTE_ADDR' => $ip];
    if ($user !== NULL) {
      $server['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode("$user:$pass");
    }
    $request = Request::create('/', 'GET', [], [], [], $server);
    return $middleware->handle($request, ShieldMiddleware::MAIN_REQUEST, TRUE);
  }

}
