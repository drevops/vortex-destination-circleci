<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Drupal\entity_usage\SiteDomains;
use Drupal\entity_usage\UrlToEntity;
use Drupal\entity_usage\UrlToEntityIntegrations\PublicFileIntegration;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests \Drupal\entity_usage\UrlToEntityIntegrations\PublicFileIntegration.
 *
 * @group entity_usage
 */
#[CoversClass(PublicFileIntegration::class)]
#[Group('entity_usage')]
class PublicFileIntegrationTest extends UnitTestCase {

  /**
   * Creates a SiteDomains instance from a raw site_domains config array.
   */
  private function makeSiteDomains(array $site_domains): SiteDomains {
    $request_context = new RequestContext();
    $request_context->setCompleteBaseUrl('http://example.com');
    return new SiteDomains(
      $this->getConfigFactoryStub(['entity_usage.settings' => ['site_domains' => $site_domains]]),
      $request_context,
      $this->createMock(LocalStream::class)
    );
  }

  /**
   * Creates a file entity query mock expecting a specific URI condition.
   */
  private function makeFileQuery(string $expected_uri, array $result): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->with('uri', $expected_uri)->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($result);
    return $query;
  }

  /**
   * Creates an EntityTypeManagerInterface mock backed by the given query.
   */
  private function makeEntityTypeManager(QueryInterface $query): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('file')->willReturn($storage);
    return $etm;
  }

  /**
   * Creates a UrlToEntityEvent for the given URL and request path.
   */
  private function makeEvent(string $unprocessedUrl, string $pathInfo, ?array $enabledTypes = NULL): UrlToEntityEvent {
    return new UrlToEntityEvent(Request::create($pathInfo), $pathInfo, $enabledTypes, $unprocessedUrl);
  }

  /**
   * Tests that a LocalStream whose domain is absent from config throws.
   */
  public function testLocalStreamDomainNotConfiguredThrows(): void {
    $request_context = new RequestContext();
    $request_context->setCompleteBaseUrl('http://another.com');
    $siteDomains = new SiteDomains(
      $this->getConfigFactoryStub([
        'entity_usage.settings' => ['site_domains' => [['host' => 'other.com', 'path' => '']]],
      ]),
      $request_context,
      $this->createMock(LocalStream::class)
    );
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $this->expectException(\LogicException::class);
    new PublicFileIntegration(
      $this->createMock(EntityTypeManagerInterface::class),
      $stream,
      $siteDomains,
    );
  }

  /**
   * Tests resolving an absolute URL in the files directory to a file entity.
   */
  public function testGetFileFromPathAbsoluteUrl(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $query = $this->makeFileQuery('public://image.jpg', [42 => 42]);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $event = $this->makeEvent('http://example.com/sites/default/files/image.jpg', '/node/1');
    $integration->getFileFromPath($event);
    $this->assertSame(['type' => 'file', 'id' => 42], $event->getEntityInfo());
  }

  /**
   * Tests resolving a relative URL matching the files pattern to a file entity.
   */
  public function testGetFileFromPathRelativeUrl(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $query = $this->makeFileQuery('public://image.jpg', [42 => 42]);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $event = $this->makeEvent(
      '/sites/default/files/image.jpg',
      '/sites/default/files/image.jpg',
    );
    $integration->getFileFromPath($event);
    $this->assertSame(['type' => 'file', 'id' => 42], $event->getEntityInfo());
  }

  /**
   * Tests that the subdir prefix is stripped before matching the files pattern.
   */
  public function testGetFileFromPathSubdirStripped(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '/subdir']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/subdir/sites/default/files');

    $query = $this->makeFileQuery('public://image.jpg', [7 => 7]);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $event = $this->makeEvent(
      '/subdir/sites/default/files/image.jpg',
      '/sites/default/files/image.jpg',
    );
    $integration->getFileFromPath($event);
    $this->assertSame(['type' => 'file', 'id' => 7], $event->getEntityInfo());
  }

  /**
   * Tests resolving an absolute CDN URL to a file via the externalUrl check.
   */
  public function testGetFileFromPathCdnAbsoluteUrl(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(StreamWrapperInterface::class);
    $stream->method('getExternalUrl')->willReturn('https://cdn.example.com/files');

    $query = $this->makeFileQuery('public://image.jpg', [99 => 99]);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $event = $this->makeEvent('https://cdn.example.com/files/image.jpg', '/image.jpg');
    $integration->getFileFromPath($event);
    $this->assertSame(['type' => 'file', 'id' => 99], $event->getEntityInfo());
  }

  /**
   * Tests that a URL not matching the CDN base returns no entity.
   */
  public function testGetFileFromPathNonLocalStreamUrlNotMatchingBase(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(StreamWrapperInterface::class);
    $stream->method('getExternalUrl')->willReturn('https://cdn.example.com/files');

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects($this->never())->method('getStorage');

    $integration = new PublicFileIntegration($etm, $stream, $siteDomains);

    $event = $this->makeEvent('http://example.com/node/1', '/node/1');
    $integration->getFileFromPath($event);
    $this->assertNull($event->getEntityInfo());
  }

  /**
   * Tests that no file lookup occurs when the file entity type is not tracked.
   */
  public function testGetFileFromPathFileNotTracked(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects($this->never())->method('getStorage');

    $integration = new PublicFileIntegration($etm, $stream, $siteDomains);

    $event = $this->makeEvent(
      'http://example.com/sites/default/files/image.jpg',
      '/sites/default/files/image.jpg',
      ['node'],
    );
    $integration->getFileFromPath($event);
    $this->assertNull($event->getEntityInfo());
  }

  /**
   * Tests that a URL matching the files directory with no result returns NULL.
   */
  public function testGetFileFromPathFileNotFound(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $query = $this->makeFileQuery('public://image.jpg', []);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $event = $this->makeEvent('http://example.com/sites/default/files/image.jpg', '/node/1');
    $integration->getFileFromPath($event);
    $this->assertNull($event->getEntityInfo());
  }

  /**
   * Tests that a URL not matching the files base or pattern returns no entity.
   */
  public function testGetFileFromPathUrlNotMatching(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects($this->never())->method('getStorage');

    $integration = new PublicFileIntegration($etm, $stream, $siteDomains);

    $event = $this->makeEvent('http://other.com/image.jpg', '/image.jpg');
    $integration->getFileFromPath($event);
    $this->assertNull($event->getEntityInfo());
  }

  /**
   * Tests that a percent-encoded absolute URL is decoded to the file URI.
   */
  public function testGetFileFromPathUrlEncodedAbsoluteUrl(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $query = $this->makeFileQuery('public://my file.jpg', [5 => 5]);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $event = $this->makeEvent(
      'http://example.com/sites/default/files/my%20file.jpg',
      '/node/1',
    );
    $integration->getFileFromPath($event);
    $this->assertSame(['type' => 'file', 'id' => 5], $event->getEntityInfo());
  }

  /**
   * Tests that a percent-encoded relative path is decoded to the file URI.
   */
  public function testGetFileFromPathUrlEncodedRelativePath(): void {
    $siteDomains = $this->makeSiteDomains([['host' => 'example.com', 'path' => '']]);
    $stream = $this->createMock(LocalStream::class);
    $stream->method('getExternalUrl')->willReturn('http://example.com/sites/default/files');

    $query = $this->makeFileQuery('public://my file.jpg', [5 => 5]);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $event = $this->makeEvent('/other', '/sites/default/files/my%20file.jpg');
    $integration->getFileFromPath($event);
    $this->assertSame(['type' => 'file', 'id' => 5], $event->getEntityInfo());
  }

  /**
   * Tests CDN file tracking when the stream wrapper URL is auto-discovered.
   */
  public function testCdnUrlTrackedViaStreamWrapperAutoDiscovery(): void {
    $stream = $this->createMock(StreamWrapperInterface::class);
    $stream->method('getExternalUrl')->willReturn('https://cdn.example.com/files');

    $configFactory = $this->getConfigFactoryStub([
      'entity_usage.settings' => ['site_domains' => []],
    ]);
    $request_context = new RequestContext();
    $request_context->setCompleteBaseUrl('https://example.com');
    $siteDomains = new SiteDomains($configFactory, $request_context, $stream);

    $query = $this->makeFileQuery('public://image.jpg', [7 => 7]);
    $integration = new PublicFileIntegration($this->makeEntityTypeManager($query), $stream, $siteDomains);

    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber($integration);

    $pathProcessor = $this->createMock(InboundPathProcessorInterface::class);
    $pathProcessor->method('processInbound')->willReturnArgument(0);
    $urlToEntity = new UrlToEntity($pathProcessor, $configFactory, $dispatcher, $siteDomains);

    $result = $urlToEntity->findEntityIdByUrl('https://cdn.example.com/files/image.jpg');
    $this->assertSame(['type' => 'file', 'id' => 7], $result);
  }

}
