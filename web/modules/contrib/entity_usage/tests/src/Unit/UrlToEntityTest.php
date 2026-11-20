<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Unit;

// cspell:ignore mnchen
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\Url;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Drupal\entity_usage\SiteDomains;
use Drupal\entity_usage\UrlToEntity;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests \Drupal\entity_usage\UrlToEntity.
 *
 * @group entity_usage
 */
#[CoversClass(UrlToEntity::class)]
#[CoversClass(SiteDomains::class)]
#[Group('entity_usage')]
class UrlToEntityTest extends UnitTestCase {

  /**
   * Tests \Drupal\entity_usage\UrlToEntity::findEntityIdByRoutedUrl()
   */
  #[TestWith(['node'])]
  #[TestWith(['my1entity'])]
  public function testFindEntityIdByRoutedUrl(string $entity_type_id): void {
    $configFactory = $this->getConfigFactoryStub([
      'entity_usage.settings' => [
        'site_domains' => [['host' => 'example.com', 'path' => '']],
      ],
    ]);
    $request_context = new RequestContext();
    $request_context->setCompleteBaseUrl('https://example.com');
    $url_to_entity = new UrlToEntity(
      $this->createMock(InboundPathProcessorInterface::class),
      $configFactory,
      $this->createMock(EventDispatcherInterface::class),
      new SiteDomains($configFactory, $request_context, $this->createMock(LocalStream::class)),
    );

    $url = new Url('entity.' . $entity_type_id . '.canonical', [$entity_type_id => 1]);
    $this->assertSame(['type' => $entity_type_id, 'id' => 1], $url_to_entity->findEntityIdByRoutedUrl($url));
  }

  /**
   * Tests \Drupal\entity_usage\UrlToEntity::findEntityIdByUrl()
   */
  #[TestWith(['/node/1', 1])]
  #[TestWith(['/path/node/1', 1])]
  #[TestWith(['/Path/node/1', 1])]
  #[TestWith(['http://xn--mnchen-3ya.de/path/node/1', 1])]
  #[TestWith(['http://münchen.de/path/node/1', 1])]
  #[TestWith(['http://müncheN.de/Path/nOde/1', 1])]
  #[TestWith(['https://example.com/node/1', 1])]
  #[TestWith(['https://example.com/', NULL])]
  #[TestWith(['https://example.com', NULL])]
  #[TestWith(['http://xn--mnchen-3ya.de/node/1', NULL])]
  #[TestWith(['http://xn--mnchen-3ya.de/example.com/1', NULL])]
  #[TestWith(['/pathway/node/1', NULL])]
  #[TestWith(['http://xn--mnchen-3ya.de/path', NULL])]
  #[TestWith(['', NULL])]
  #[TestWith(['http://example.com/<front>', NULL])]
  #[TestWith(['http://example.com/<none>', NULL])]
  #[TestWith(['/path', NULL])]
  public function testFindEntityIdByUrl(string $url, ?int $expected_id): void {
    $path_processor = $this->createMock(InboundPathProcessorInterface::class);
    $path_processor->method('processInbound')->willReturnArgument(0);
    $event_dispatcher = new EventDispatcher();
    $event_dispatcher->addListener(Events::URL_TO_ENTITY, function (UrlToEntityEvent $event) {
      if ($event->pathProcessedUrl === '/node/1') {
        $event->setEntityInfo('node', 1);
      }
    });
    $config_factory = $this->getConfigFactoryStub([
      'entity_usage.settings' => [
        'site_domains' => [
          ['host' => 'xn--mnchen-3ya.de', 'path' => '/path'],
          ['host' => 'example.com', 'path' => ''],
        ],
      ],
    ]);
    $request_context = new RequestContext();
    $request_context->setCompleteBaseUrl('https://example.com');
    $url_to_entity = new UrlToEntity(
      $path_processor,
      $config_factory,
      $event_dispatcher,
      new SiteDomains($config_factory, $request_context, $this->createMock(LocalStream::class)),
    );

    $entity = $url_to_entity->findEntityIdByUrl($url);
    $this->assertSame($expected_id, $entity['id'] ?? NULL);
  }

  /**
   * Builds a minimal UrlToEntity with an optional enabled entity types filter.
   */
  private function makeUrlToEntity(?array $enabledTypes = NULL): UrlToEntity {
    $config = [
      'entity_usage.settings' => [
        'site_domains' => [['host' => 'example.com', 'path' => '']],
      ],
    ];
    if ($enabledTypes !== NULL) {
      $config['entity_usage.settings']['track_enabled_target_entity_types'] = $enabledTypes;
    }
    $configFactory = $this->getConfigFactoryStub($config);
    $request_context = new RequestContext();
    $request_context->setCompleteBaseUrl('https://example.com');
    return new UrlToEntity(
      $this->createMock(InboundPathProcessorInterface::class),
      $configFactory,
      $this->createMock(EventDispatcherInterface::class),
      new SiteDomains($configFactory, $request_context, $this->createMock(LocalStream::class)),
    );
  }

  /**
   * Tests that a non-routed URL returns NULL.
   */
  public function testFindEntityIdByRoutedUrlNonRoutedReturnsNull(): void {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(FALSE);
    $this->assertNull($this->makeUrlToEntity()->findEntityIdByRoutedUrl($url));
  }

  /**
   * Tests that a non-entity route name returns NULL.
   */
  public function testFindEntityIdByRoutedUrlNonEntityRouteReturnsNull(): void {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(TRUE);
    $url->method('getRouteName')->willReturn('some.other.route');
    $this->assertNull($this->makeUrlToEntity()->findEntityIdByRoutedUrl($url));
  }

  /**
   * Tests that an entity route without the entity ID parameter returns NULL.
   */
  public function testFindEntityIdByRoutedUrlMissingRouteParamReturnsNull(): void {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(TRUE);
    $url->method('getRouteName')->willReturn('entity.node.canonical');
    $url->method('getRouteParameters')->willReturn([]);
    $this->assertNull($this->makeUrlToEntity()->findEntityIdByRoutedUrl($url));
  }

  /**
   * Tests that an entity route for a non-tracked entity type returns NULL.
   */
  public function testFindEntityIdByRoutedUrlUntrackedEntityTypeReturnsNull(): void {
    $url = new Url('entity.node.canonical', ['node' => 1]);
    $this->assertNull($this->makeUrlToEntity(['user'])->findEntityIdByRoutedUrl($url));
  }

}
