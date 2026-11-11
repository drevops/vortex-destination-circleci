<?php

declare(strict_types=1);

namespace Drupal\entity_usage\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\entity_usage\EntityUsageTrackManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Deletes the container if default language has changed.
 */
readonly class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    private RouteBuilderInterface $routeBuilder,
    private CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private EntityUsageTrackManager $entityUsageTrackManager,
  ) {
  }

  /**
   * Rebuilds router and clears render cache when local tasks are changed.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $saved_config = $event->getConfig();
    if (!$saved_config->isNew() && $saved_config->getName() == 'entity_usage.settings') {
      if ($event->isChanged('local_task_enabled_entity_types')) {
        $this->routeBuilder->setRebuildNeeded();
        $this->cacheTagsInvalidator->invalidateTags(['rendered']);
      }
      if ($event->isChanged('track_enabled_plugins')) {
        $this->entityUsageTrackManager->clearCachedDefinitions();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    return $events;
  }

}
