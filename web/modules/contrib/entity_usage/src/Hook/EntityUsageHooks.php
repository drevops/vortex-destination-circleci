<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\entity_usage\EntityUsageTrackManager;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for entity_usage.
 */
class EntityUsageHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly EntityUsageInterface $entityUsage,
    private readonly EntityUsageTrackManager $entityUsageTrackManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {

  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    switch ($route_name) {
      // Main module help for the entity_usage module.
      case 'help.page.entity_usage':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Track usage of entities referenced by other entities.') . '</p>';
        return $output;

      default:
        return '';
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('field_storage_config_delete')]
  public function fieldStorageConfigDelete(FieldStorageConfigInterface $field): void {
    // Delete all usages tracked through this field.
    $this->entityUsage->deleteByField($field->getTargetEntityTypeId(), $field->getName());
  }

  /**
   * Implements hook_module_preuninstall().
   */
  #[Hook('module_preuninstall')]
  public function modulePreuninstall(string $module, bool $is_syncing = FALSE): void {
    if ($is_syncing) {
      return;
    }

    $source_entity_classes = $provided_plugins = [];
    foreach ($this->entityUsageTrackManager->getDefinitions() as $plugin_id => $definition) {
      if ($definition['provider'] === $module) {
        $provided_plugins[] = $plugin_id;
      }
      else {
        $source_entity_classes[] = $definition['source_entity_class'];
      }
    }
    if (empty($provided_plugins)) {
      // The module does not provide any entity usage tracking plugins. There is
      // nothing to do.
      return;
    }
    $config = $this->configFactory->getEditable('entity_usage.settings');
    $config->set('track_enabled_plugins', array_diff($config->get('track_enabled_plugins') ?: [], $provided_plugins));
    // Remove any entity types that are not supported.
    $source_entity_classes = array_unique($source_entity_classes);
    $source_entity_types = $config->get('track_enabled_source_entity_types') ?? [];
    foreach ($source_entity_types as $key => $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
      if ($entity_type instanceof EntityTypeInterface) {
        foreach ($source_entity_classes as $source_entity_class) {
          if ($entity_type->entityClassImplements($source_entity_class)) {
            continue 2;
          }
        }
      }
      unset($source_entity_types[$key]);
    }
    $config->set('track_enabled_source_entity_types', array_values($source_entity_types))->save();
  }

}
