<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\ModelerApiModelOwner;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Virtual plugin representing a field mapping in a migration.
 *
 * This is not a real Drupal plugin but a wrapper that allows field mappings
 * to be treated as components in the Modeler API. Each field mapping gateway
 * uses this plugin type with configuration containing the field name and
 * process plugin chain.
 */
final class MigrateFieldPlugin implements PluginInspectionInterface, ConfigurableInterface {

  /**
   * The plugin ID.
   */
  protected string $pluginId;

  /**
   * The plugin configuration.
   */
  protected array $configuration;

  /**
   * Constructs a MigrateFieldPlugin.
   *
   * @param string $pluginId
   *   The plugin ID.
   * @param array $configuration
   *   The plugin configuration.
   */
  public function __construct(string $pluginId, array $configuration = []) {
    $this->pluginId = $pluginId;
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    return [
      'id' => $this->pluginId,
      'label' => new TranslatableMarkup('Field Mapping'),
      'title' => new TranslatableMarkup('Field Mapping'),
      'description' => new TranslatableMarkup('Maps a source field to a destination field with optional process plugins.'),
      'provider' => 'migrate_plus',
      'schema_key' => 'migrate_plus.gateway.field',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'process' => '',
    ];
  }

}
