<?php

declare(strict_types=1);

namespace Drupal\entity_usage;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Url;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a service to determine if a URL references an entity.
 *
 * Subscribers to the Events::URL_TO_ENTITY event can use different methods for
 * retrieving entity information from a URL string, such as entity routing,
 * mapping to files, checking redirects, etc. The first subscriber that is able
 * to identify an entity from the URL is expected to use
 * UrlToEntityEvent::setEntityInfo() to store the entity information in the
 * event object, which will also stop its propagation to further subscribers.
 *
 * @see \Drupal\entity_usage\Events\Events::URL_TO_ENTITY
 * @see \Drupal\entity_usage\Events\UrlToEntityEvent
 */
class UrlToEntity implements UrlToEntityInterface {

  /**
   * The list of enabled entity types.
   *
   * @var string[]|null
   */
  private ?array $enabledTargetEntityTypes;

  public function __construct(private readonly InboundPathProcessorInterface $pathProcessor, ConfigFactoryInterface $configFactory, private readonly EventDispatcherInterface $eventDispatcher, private readonly SiteDomains $siteDomains) {
    $this->enabledTargetEntityTypes = $configFactory->get('entity_usage.settings')->get('track_enabled_target_entity_types');
  }

  /**
   * {@inheritdoc}
   */
  public function findEntityIdByUrl(string $url): ?array {
    if (empty($url)) {
      return NULL;
    }
    // URLs are case-insensitive in Drupal.
    $url = mb_strtolower($url);

    $original_url = $url;
    $url = $this->siteDomains->getInternalUrl($url);
    if ($url === NULL) {
      return NULL;
    }
    $url = ltrim($url, '/');
    $parsed_url = UrlHelper::parse($url);
    if ($parsed_url['path'] == '<front>' || $parsed_url['path'] == '<none>') {
      return NULL;
    }

    // If passed an invalid or malformed URL exit early without triggering an
    // exception.
    try {
      $request = Request::create('/' . $url);
    }
    catch (BadRequestException) {
      return NULL;
    }

    $path_processed_url = $this->pathProcessor->processInbound('/' . $url, $request);
    $event = new UrlToEntityEvent($request, $path_processed_url, $this->enabledTargetEntityTypes, $original_url);
    $this->eventDispatcher->dispatch($event, Events::URL_TO_ENTITY);
    return $event->getEntityInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function findEntityIdByRoutedUrl(Url $url): ?array {
    if (!$url->isRouted()) {
      return NULL;
    }

    if (preg_match(static::ENTITY_ROUTE_PATTERN, $url->getRouteName(), $matches)) {
      $entity_type_id = $matches[1];
      if ($this->isEntityTypeTracked($entity_type_id) && isset($url->getRouteParameters()[$entity_type_id])) {
        return [
          'type' => $entity_type_id,
          'id' => $url->getRouteParameters()[$entity_type_id],
        ];
      }
    }

    return NULL;
  }

  /**
   * Determines if an entity type is tracked.
   *
   * @param string $entity_type_id
   *   The entity type ID to check.
   *
   * @return bool
   *   Determines if an entity type is tracked.
   */
  protected function isEntityTypeTracked(string $entity_type_id): bool {
    // Every entity type is tracked if not set.
    return $this->enabledTargetEntityTypes === NULL || in_array($entity_type_id, $this->enabledTargetEntityTypes, TRUE);
  }

}
