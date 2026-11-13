<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Unit;

// cspell:ignore mnchen
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\entity_usage\SiteDomains;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests \Drupal\entity_usage\SiteDomains.
 *
 * @group entity_usage
 */
#[CoversClass(SiteDomains::class)]
#[Group('entity_usage')]
class SiteDomainsTest extends UnitTestCase {

  /**
   * Creates a SiteDomains instance from a raw site_domains config array.
   */
  private function makeSiteDomains(array $site_domains, RequestContext $requestContext, StreamWrapperInterface $publicStream): SiteDomains {
    return new SiteDomains($this->getConfigFactoryStub([
      'entity_usage.settings' => ['site_domains' => $site_domains],
    ]), $requestContext, $publicStream);
  }

  /**
   * Creates a RequestContext mock with a given complete base URL.
   */
  private function makeRequestContext(string $baseUrl): RequestContext {
    $ctx = $this->createMock(RequestContext::class);
    $ctx->method('getCompleteBaseUrl')->willReturn($baseUrl);
    return $ctx;
  }

  /**
   * Creates a StreamWrapperInterface mock with a given external URL.
   */
  private function makeStream(string $externalUrl): StreamWrapperInterface {
    $stream = $this->createMock(StreamWrapperInterface::class);
    $stream->method('getExternalUrl')->willReturn($externalUrl);
    return $stream;
  }

  /**
   * Tests that an old string-format site_domains config is converted.
   */
  public function testConstructorBcShim(): void {
    $siteDomains = $this->makeSiteDomains(
      ['example.com', 'example.com/subdir'],
      $this->makeRequestContext(''),
      $this->makeStream(''),
    );
    $this->assertSame([
      ['host' => 'example.com', 'path' => ''],
      ['host' => 'example.com', 'path' => '/subdir'],
    ], $siteDomains->list);
    $this->assertSame('/subdir', $siteDomains->subPath);
  }

  /**
   * Tests that stringsToConfig skips entries with no host.
   */
  public function testStringsToConfigSkipsMalformedHost(): void {
    $this->assertSame([], SiteDomains::stringsToConfig(['http:///']));
  }

  /**
   * Tests that stringsToConfig skips entries that fail idn_to_ascii.
   */
  public function testStringsToConfigSkipsInvalidIdn(): void {
    $this->assertSame([], SiteDomains::stringsToConfig(['example..com']));
  }

  /**
   * Tests a URL containing but not matching a host returns NULL.
   */
  public function testGetInternalUrlContainingHostStringReturnsNull(): void {
    $siteDomains = $this->makeSiteDomains(
      [['host' => 'xn--mnchen-3ya.de', 'path' => '/path']],
      $this->makeRequestContext(''),
      $this->makeStream(''),
    );
    $this->assertNull($siteDomains->getInternalUrl('http://xn--mnchen-3ya.de.fake/path'));
  }

  /**
   * Tests that an external URL with no path returns NULL if config has a path.
   */
  public function testGetInternalUrlExternalNoPathConfigHasPath(): void {
    $siteDomains = $this->makeSiteDomains(
      [['host' => 'example.com', 'path' => '/sub']],
      $this->makeRequestContext(''),
      $this->makeStream(''),
    );
    $this->assertNull($siteDomains->getInternalUrl('http://example.com'));
  }

  /**
   * Tests that an empty string passes through unchanged.
   */
  public function testGetInternalUrlEmptyStringPassesThrough(): void {
    $siteDomains = $this->makeSiteDomains(
      [],
      $this->makeRequestContext(''),
      $this->makeStream(''),
    );
    $this->assertSame('', $siteDomains->getInternalUrl(''));
  }

  /**
   * Tests that the site base URL is auto-discovered from RequestContext.
   */
  public function testAutoDiscoversFromRequestContext(): void {
    $siteDomains = $this->makeSiteDomains(
      [],
      $this->makeRequestContext('https://example.com'),
      $this->makeStream(''),
    );
    $this->assertContains(['host' => 'example.com', 'path' => ''], $siteDomains->list);
    $this->assertSame('', $siteDomains->subPath);
  }

  /**
   * Tests that a subdirectory install is auto-discovered from RequestContext.
   */
  public function testAutoDiscoversSubdirFromRequestContext(): void {
    $siteDomains = $this->makeSiteDomains(
      [],
      $this->makeRequestContext('https://example.com/sub'),
      $this->makeStream(''),
    );
    $this->assertContains(['host' => 'example.com', 'path' => '/sub'], $siteDomains->list);
    $this->assertSame('/sub', $siteDomains->subPath);
  }

  /**
   * Tests that a LocalStream instance is not auto-discovered.
   */
  public function testLocalStreamNotAutoDiscovered(): void {
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');
    $siteDomains = $this->makeSiteDomains(
      [],
      $this->makeRequestContext(''),
      $stream
    );
    $this->assertSame([], $siteDomains->list);
  }

  /**
   * Tests that the public stream wrapper URL is auto-discovered.
   */
  public function testAutoDiscoversFromStreamWrapper(): void {
    $siteDomains = $this->makeSiteDomains(
      [],
      $this->makeRequestContext(''),
      $this->makeStream('https://cdn.example.com/files'),
    );
    $this->assertContains(['host' => 'cdn.example.com', 'path' => '/files'], $siteDomains->list);
  }

  /**
   * Tests that the CDN path is not treated as the site subdirectory.
   */
  public function testStreamWrapperDoesNotSetSubPath(): void {
    $siteDomains = $this->makeSiteDomains(
      [],
      $this->makeRequestContext('https://example.com'),
      $this->makeStream('https://cdn.example.com/files'),
    );
    $this->assertSame('', $siteDomains->subPath);
  }

  /**
   * Tests that auto-discovery does not duplicate an entry already in config.
   */
  public function testAutoDiscoveryDeduplication(): void {
    $siteDomains = $this->makeSiteDomains(
      [['host' => 'example.com', 'path' => '']],
      $this->makeRequestContext('https://example.com'),
      $this->makeStream(''),
    );
    $count = count(array_filter(
      $siteDomains->list,
      fn($e) => $e === ['host' => 'example.com', 'path' => ''],
    ));
    $this->assertSame(1, $count);
  }

  /**
   * Tests that an empty RequestContext URL (e.g. CLI) adds nothing.
   */
  public function testAutoDiscoveryIgnoresEmptyRequestContext(): void {
    $siteDomains = $this->makeSiteDomains(
      [],
      $this->makeRequestContext(''),
      $this->makeStream(''),
    );
    $this->assertSame([], $siteDomains->list);
  }

}
