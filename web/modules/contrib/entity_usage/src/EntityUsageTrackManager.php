<?php

declare(strict_types=1);

namespace Drupal\entity_usage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\entity_usage\Attribute\EntityUsageTrack as AttributeEntityUsageTrack;

/**
 * Manages entity_usage track plugins.
 */
class EntityUsageTrackManager extends DefaultPluginManager {

  /**
   * A list of classes that sources entities can implement.
   *
   * @var class-string[]
   */
  protected array $sourceEntityClasses = [];

  /**
   * A list of instantiated plugins, keyed by plugin ID.
   *
   * @var array<string, \Drupal\entity_usage\EntityUsageTrackInterface>
   */
  protected array $instantiatedPlugins = [];

  /**
   * A list of the plugin IDs of all inline entity usage tracking plugins.
   *
   * @var string[]|null
   */
  protected ?array $inlinePluginIds = NULL;

  /**
   * A list of all the inline entity type IDs.
   *
   * @var string[]|null
   */
  protected ?array $inlineEntityTypeIds = NULL;

  /**
   * A list of all the source tracked entity type IDs.
   *
   * @var string[]|null
   */
  protected ?array $sourceTrackedEntityTypeIds = NULL;

  /**
   * Constructs a new EntityUsageTrackManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, protected EntityTypeManagerInterface $entityTypeManager, protected ConfigFactoryInterface $configFactory) {
    parent::__construct(
      'Plugin/EntityUsage/Track',
      $namespaces,
      $module_handler,
      EntityUsageTrackInterface::class,
      AttributeEntityUsageTrack::class,
    );
    $this->alterInfo('entity_usage_track_info');
    $this->setCacheBackend($cache_backend, 'entity_usage_track_plugins');
  }

  /**
   * Determines if the tracking plugins support this entity type as a source.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to check.
   *
   * @return bool
   *   TRUE if the entity type is supported as a source entity, FALSE if not.
   */
  public function isEntityTypeSource(EntityTypeInterface $entity_type): bool {
    if (empty($this->sourceEntityClasses)) {
      foreach ($this->getDefinitions() as $definition) {
        $this->sourceEntityClasses[] = $definition['source_entity_class'];
      }
      $this->sourceEntityClasses = array_unique($this->sourceEntityClasses);
    }
    foreach ($this->sourceEntityClasses as $source_entity_class) {
      if ($entity_type->entityClassImplements($source_entity_class)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns entity type IDs to track as sources for a batch recreate.
   *
   * Excludes inline entity type IDs. When no source entity types are
   * configured, all content entity types are included except 'file' and
   * 'user'.
   *
   * @return string[]
   *   Entity type IDs to track as sources.
   */
  public function getSourceEntityTypeIds(): array {
    if (!isset($this->sourceTrackedEntityTypeIds)) {
      $this->sourceTrackedEntityTypeIds = [];
      $to_track = $this->configFactory->get('entity_usage.settings')->get('track_enabled_source_entity_types');
      $entity_types = $this->entityTypeManager->getDefinitions();
      if (!is_array($to_track)) {
        $to_skip = array_merge($this->getInlineEntityTypeIds(), ['file', 'user']);
        foreach ($entity_types as $entity_type_id => $entity_type) {
          if (in_array($entity_type_id, $to_skip, TRUE)) {
            continue;
          }
          if ($entity_type->hasKey('id') && $entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')) {
            $this->sourceTrackedEntityTypeIds[] = $entity_type_id;
          }
        }
      }
      else {
        $this->sourceTrackedEntityTypeIds = array_intersect($to_track, array_keys($entity_types));
      }
    }
    return $this->sourceTrackedEntityTypeIds;
  }

  /**
   * Returns entity type IDs that are inline across all plugins.
   *
   * @return string[]
   *   An array of entity type IDs.
   */
  public function getInlineEntityTypeIds(): array {
    if (!is_array($this->inlineEntityTypeIds)) {
      $this->inlineEntityTypeIds = [];
      foreach ($this->getInlinePluginIds() as $plugin_id) {
        $plugin = $this->createInstance($plugin_id);
        assert($plugin instanceof EntityUsageInlineTrackingInterface);
        $this->inlineEntityTypeIds = array_merge($this->inlineEntityTypeIds, $plugin->getInlineEntityTypeIds());
      }
      $this->inlineEntityTypeIds = array_unique($this->inlineEntityTypeIds);
    }
    return $this->inlineEntityTypeIds;
  }

  /**
   * Returns the plugin IDs of all inline entity usage tracking plugins.
   *
   * @return string[]
   *   An array of plugin IDs.
   */
  public function getInlinePluginIds(): array {
    if (!is_array($this->inlinePluginIds)) {
      $this->inlinePluginIds = [];
      foreach ($this->getDefinitions() as $definition) {
        if (is_subclass_of($definition['class'], EntityUsageInlineTrackingInterface::class)) {
          $this->inlinePluginIds[] = $definition['id'];
        }
      }
    }
    return $this->inlinePluginIds;
  }

  /**
   * Instantiates the enabled tracking plugins.
   *
   * Inline entity usage tracking plugins are always included regardless of the
   * configured value.
   *
   * @param array|null $enabled_plugin_ids
   *   The value of the 'track_enabled_plugins' config setting, or NULL to use
   *   all defined plugins.
   * @param string|null $exclude_plugin_id
   *   A plugin ID to omit from the result (used by inline plugins themselves
   *   to avoid recursing into their own implementation).
   *
   * @return array<string, \Drupal\entity_usage\EntityUsageTrackInterface>
   *   The enabled plugin instances keyed by plugin ID.
   */
  public function getEnabledPlugins(?array $enabled_plugin_ids, ?string $exclude_plugin_id = NULL): array {
    $all_plugin_ids = array_keys($this->getDefinitions());
    $enabled_plugin_ids ??= $all_plugin_ids;
    // Always include inline entity usage tracking plugins.
    $enabled_plugin_ids = array_unique(array_merge($enabled_plugin_ids, $this->getInlinePluginIds()));

    $plugins = [];
    foreach (array_intersect($all_plugin_ids, $enabled_plugin_ids) as $plugin_id) {
      if ($plugin_id === $exclude_plugin_id) {
        continue;
      }
      $plugins[$plugin_id] = $this->createInstance($plugin_id);
    }
    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    parent::clearCachedDefinitions();
    $this->sourceEntityClasses = [];
    $this->instantiatedPlugins = [];
    $this->inlinePluginIds = NULL;
    $this->inlineEntityTypeIds = NULL;
    $this->sourceTrackedEntityTypeIds = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): EntityUsageTrackInterface {
    if ($configuration === [] && isset($this->instantiatedPlugins[$plugin_id])) {
      return $this->instantiatedPlugins[$plugin_id];
    }
    $plugin = parent::createInstance($plugin_id, $configuration);
    assert($plugin instanceof EntityUsageTrackInterface);
    if ($configuration === []) {
      $this->instantiatedPlugins[$plugin_id] = $plugin;
    }
    return $plugin;
  }

}
