<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Validation;

/**
 * Provides constraint callbacks for migrate plugin validation.
 */
final class MigrateConstraintCallbacks {

  /**
   * Returns all available process plugin IDs as options.
   *
   * Returns an associative array of plugin_id => label for use in
   * Choice constraint callbacks. The YamlSchemaLookup will convert
   * this to options for the frontend dropdown.
   *
   * @return array
   *   An associative array of process plugin IDs => labels.
   */
  public static function getProcessPluginOptions(): array {
    /** @var \Drupal\Core\Plugin\DefaultPluginManager $processManager */
    $processManager = \Drupal::service('plugin.manager.migrate.process');
    $definitions = $processManager->getDefinitions();

    $options = [];
    foreach ($definitions as $id => $definition) {
      $label = $definition['title'] ?? $definition['label'] ?? $id;
      $options[$id] = (string) $label;
    }

    asort($options);
    return $options;
  }

  /**
   * Returns all available process plugin IDs.
   *
   * @return array
   *   An array of process plugin IDs.
   */
  public static function getProcessPluginIds(): array {
    return array_keys(static::getProcessPluginOptions());
  }

}
