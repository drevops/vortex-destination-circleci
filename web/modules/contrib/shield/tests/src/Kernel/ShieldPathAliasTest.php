<?php

namespace Drupal\Tests\shield\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\shield\ShieldMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests that a configured path covers both its alias and its system path.
 *
 * Shield cannot use the alias manager before the module files are loaded, so
 * it queries the alias repository directly. Either spelling of a path in the
 * configuration has to protect, or let through, the other spelling as well:
 * otherwise a site that includes only /about leaves /node/1 unprotected.
 *
 * @coversDefaultClass \Drupal\shield\ShieldMiddleware
 *
 * @group shield
 */
class ShieldPathAliasTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
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

    $this->installEntitySchema('path_alias');
    $this->installConfig(['language', 'shield']);
    $this->config('shield.settings')
      ->set('shield_enable', TRUE)
      // The tests run under the CLI SAPI, which Shield lets through by
      // default.
      ->set('allow_cli', FALSE)
      ->set('credential_provider', 'shield')
      ->set('credentials.shield.user', 'user')
      ->set('credentials.shield.pass', 'password')
      ->save();

    PathAlias::create(['path' => '/node/1', 'alias' => '/about'])->save();
  }

  /**
   * Tests that a path listed under either spelling covers both.
   *
   * @param string $paths
   *   The configured paths.
   * @param int $method
   *   Either ShieldMiddleware::EXCLUDE_METHOD or ::INCLUDE_METHOD.
   * @param array $expected
   *   The expected X-Shield-Status per requested path.
   *
   * @covers ::checkPathAllowed
   * @covers ::getPathSpellings
   *
   * @dataProvider providerPathSpellings
   */
  public function testPathSpellings(string $paths, int $method, array $expected): void {
    $this->config('shield.settings')
      ->set('paths', $paths)
      ->set('method', $method)
      ->save();

    foreach ($expected as $path => $status) {
      $this->assertShieldStatus($path, $status);
    }
  }

  /**
   * Asserts how Shield answers a request.
   *
   * @param string $path
   *   The path to request.
   * @param string $status
   *   The expected outcome: 'pending' for a challenge, or the X-Shield-Status
   *   value Shield records on a request it lets through.
   */
  protected function assertShieldStatus(string $path, string $status): void {
    $request = Request::create($path);
    $response = $this->buildMiddleware()->handle($request);
    if ($status === 'pending') {
      $this->assertSame(401, $response->getStatusCode(), $path);
      $this->assertNull($request->headers->get('X-Shield-Status'), $path);
    }
    else {
      $this->assertSame(200, $response->getStatusCode(), $path);
      $this->assertSame($status, $request->headers->get('X-Shield-Status'), $path);
    }
  }

  /**
   * Data provider for ::testPathSpellings().
   *
   * @return array[]
   *   Test cases holding the configured paths, the method and the outcome per
   *   requested path.
   */
  public static function providerPathSpellings(): array {
    return [
      'exclude alias' => [
        '/about',
        ShieldMiddleware::EXCLUDE_METHOD,
        [
          '/about' => 'skipped (path)',
          '/node/1' => 'skipped (path)',
          '/node/2' => 'pending',
          '/elsewhere' => 'pending',
        ],
      ],
      'exclude system path' => [
        '/node/1',
        ShieldMiddleware::EXCLUDE_METHOD,
        [
          '/node/1' => 'skipped (path)',
          '/about' => 'skipped (path)',
          '/node/2' => 'pending',
        ],
      ],
      'include alias' => [
        '/about',
        ShieldMiddleware::INCLUDE_METHOD,
        [
          '/about' => 'pending',
          '/node/1' => 'pending',
          '/node/2' => 'skipped (path)',
          '/' => 'skipped (path)',
        ],
      ],
      'include system path' => [
        '/node/1',
        ShieldMiddleware::INCLUDE_METHOD,
        [
          '/node/1' => 'pending',
          '/about' => 'pending',
          '/elsewhere' => 'skipped (path)',
        ],
      ],
      'wildcard on the system path' => [
        '/node/*',
        ShieldMiddleware::INCLUDE_METHOD,
        [
          '/about' => 'pending',
          '/node/1' => 'pending',
          '/elsewhere' => 'skipped (path)',
        ],
      ],
    ];
  }

  /**
   * Tests that the alias is looked up in the language the URL prefix selects.
   *
   * @covers ::checkPathAllowed
   * @covers ::splitLanguagePrefix
   * @covers ::getPathSpellings
   */
  public function testLanguageSpecificAlias(): void {
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => '', 'fr' => 'fr'])
      ->save();
    PathAlias::create(['path' => '/node/2', 'alias' => '/a-propos', 'langcode' => 'fr'])->save();
    PathAlias::create(['path' => '/node/3', 'alias' => '/team', 'langcode' => 'en'])->save();
    $this->config('shield.settings')
      ->set('paths', "/node/2\n/node/3")
      ->set('method', ShieldMiddleware::INCLUDE_METHOD)
      ->save();

    // The French alias resolves under the French prefix.
    $this->assertShieldStatus('/fr/a-propos', 'pending');

    // Without a prefix the request is in the language with the empty prefix,
    // so the English alias resolves and the French one does not.
    $this->assertShieldStatus('/team', 'pending');
    $this->assertShieldStatus('/a-propos', 'skipped (path)');

    // A language-neutral alias resolves under every prefix.
    $this->assertShieldStatus('/fr/about', 'skipped (path)');
    $this->config('shield.settings')->set('paths', '/node/1')->save();
    $this->assertShieldStatus('/fr/about', 'pending');
  }

  /**
   * Builds the middleware with a kernel that reports a passed request.
   *
   * @return \Drupal\shield\ShieldMiddleware
   *   The middleware to test.
   */
  protected function buildMiddleware(): ShieldMiddleware {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $kernel->method('handle')->willReturn(new Response('passed through'));

    return new ShieldMiddleware(
      $kernel,
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
      $this->container->get('request_stack'),
      $this->container->get('logger.channel.shield'),
      $this->container->get('path_alias.repository')
    );
  }

}
