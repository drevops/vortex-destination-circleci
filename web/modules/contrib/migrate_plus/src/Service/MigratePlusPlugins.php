<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Service;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\migrate\Plugin\MigrateDestinationPluginManager;
use Drupal\migrate\Plugin\MigrateSourcePluginManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service providing unified access to migrate source and destination plugins.
 *
 * Process plugins are not exposed directly - they are configured via YAML
 * in field mapping gateways.
 */
final class MigratePlusPlugins {

  /**
   * The source plugin manager.
   */
  protected MigrateSourcePluginManager $sourceManager;

  /**
   * The destination plugin manager.
   */
  protected MigrateDestinationPluginManager $destinationManager;

  /**
   * The plugin property definitions service.
   */
  protected PluginPropertyDefinitions $propertyDefinitions;

  /**
   * The container.
   */
  protected ContainerInterface $container;

  /**
   * Cached source plugin instances.
   *
   * @var \Drupal\Component\Plugin\PluginInspectionInterface[]|null
   */
  protected ?array $sourceInstances = NULL;

  /**
   * Cached destination plugin instances.
   *
   * @var \Drupal\Component\Plugin\PluginInspectionInterface[]|null
   */
  protected ?array $destinationInstances = NULL;

  /**
   * Constructs the MigratePlusPlugins service.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \Drupal\migrate_plus\Service\PluginPropertyDefinitions $propertyDefinitions
   *   The plugin property definitions service.
   */
  public function __construct(ContainerInterface $container, PluginPropertyDefinitions $propertyDefinitions) {
    $this->container = $container;
    $this->propertyDefinitions = $propertyDefinitions;
  }

  /**
   * Gets the source plugin manager.
   *
   * @return \Drupal\migrate\Plugin\MigrateSourcePluginManager
   *   The source plugin manager.
   */
  protected function getSourceManager(): MigrateSourcePluginManager {
    if (!isset($this->sourceManager)) {
      $this->sourceManager = $this->container->get('plugin.manager.migrate.source');
    }
    return $this->sourceManager;
  }

  /**
   * Gets the destination plugin manager.
   *
   * @return \Drupal\migrate\Plugin\MigrateDestinationPluginManager
   *   The destination plugin manager.
   */
  protected function getDestinationManager(): MigrateDestinationPluginManager {
    if (!isset($this->destinationManager)) {
      $this->destinationManager = $this->container->get('plugin.manager.migrate.destination');
    }
    return $this->destinationManager;
  }

  /**
   * Gets a stub migration for plugin instantiation.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   A stub migration.
   */
  protected function getStubMigration(): MigrationInterface {
    /** @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationManager */
    $migrationManager = $this->container->get('plugin.manager.migration');
    return $migrationManager->createStubMigration([
      'id' => 'modeler_api_stub',
      'source' => ['plugin' => 'empty'],
      'process' => [],
      'destination' => ['plugin' => 'null'],
    ]);
  }

  /**
   * Gets all available source plugin instances.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface[]
   *   An array of source plugin instances.
   */
  public function getSources(): array {
    if ($this->sourceInstances !== NULL) {
      return $this->sourceInstances;
    }

    $this->sourceInstances = [];
    $definitions = $this->getSourceManager()->getDefinitions();
    $migration = $this->getStubMigration();

    foreach ($definitions as $pluginId => $definition) {
      try {
        // Get default configuration from property definitions.
        $config = $this->propertyDefinitions->getDefaultConfig('source', $pluginId);

        // Use error handler to convert warnings to exceptions.
        set_error_handler(function ($severity, $message) {
          throw new \ErrorException($message, 0, $severity);
        });

        try {
          $instance = $this->getSourceManager()->createInstance($pluginId, $config, $migration);
          if ($instance instanceof PluginInspectionInterface) {
            $this->sourceInstances[] = $instance;
          }
        }
        finally {
          restore_error_handler();
        }
      }
      catch (\Throwable) {
        // Skip plugins that cannot be instantiated.
      }
    }

    $this->sortPlugins($this->sourceInstances);
    return $this->sourceInstances;
  }

  /**
   * Gets all available destination plugin instances.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface[]
   *   An array of destination plugin instances.
   */
  public function getDestinations(): array {
    if ($this->destinationInstances !== NULL) {
      return $this->destinationInstances;
    }

    $this->destinationInstances = [];
    $definitions = $this->getDestinationManager()->getDefinitions();
    $migration = $this->getStubMigration();

    foreach ($definitions as $pluginId => $definition) {
      try {
        // Get default configuration from property definitions.
        $config = $this->propertyDefinitions->getDefaultConfig('destination', $pluginId);

        // Use error handler to convert warnings to exceptions.
        set_error_handler(function ($severity, $message) {
          throw new \ErrorException($message, 0, $severity);
        });

        try {
          $instance = $this->getDestinationManager()->createInstance($pluginId, $config, $migration);
          if ($instance instanceof PluginInspectionInterface) {
            $this->destinationInstances[] = $instance;
          }
        }
        finally {
          restore_error_handler();
        }
      }
      catch (\Throwable) {
        // Skip plugins that cannot be instantiated.
      }
    }

    $this->sortPlugins($this->destinationInstances);
    return $this->destinationInstances;
  }

  /**
   * Sorts plugins by their label.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface[] $plugins
   *   The plugins to sort (modified in place).
   */
  protected function sortPlugins(array &$plugins): void {
    usort($plugins, function (PluginInspectionInterface $a, PluginInspectionInterface $b) {
      $labelA = $this->getPluginLabel($a);
      $labelB = $this->getPluginLabel($b);
      return strcasecmp($labelA, $labelB);
    });
  }

  /**
   * Gets a human-readable label for a plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   *
   * @return string
   *   The plugin label.
   */
  protected function getPluginLabel(PluginInspectionInterface $plugin): string {
    $definition = $plugin->getPluginDefinition();
    if (is_array($definition)) {
      return (string) ($definition['title'] ?? $definition['label'] ?? $definition['id'] ?? $plugin->getPluginId());
    }
    return $plugin->getPluginId();
  }

  /**
   * Creates a source plugin instance.
   *
   * @param string $pluginId
   *   The plugin ID.
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface|null
   *   The plugin instance or NULL if creation failed.
   */
  public function createSourceInstance(string $pluginId, array $configuration = []): ?PluginInspectionInterface {
    try {
      // Merge default configuration with provided configuration.
      $defaults = $this->propertyDefinitions->getDefaultConfig('source', $pluginId);
      $config = array_merge($defaults, $configuration);
      $migration = $this->getStubMigration();
      return $this->getSourceManager()->createInstance($pluginId, $config, $migration);
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Creates a destination plugin instance.
   *
   * @param string $pluginId
   *   The plugin ID.
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface|null
   *   The plugin instance or NULL if creation failed.
   */
  public function createDestinationInstance(string $pluginId, array $configuration = []): ?PluginInspectionInterface {
    try {
      // Merge default configuration with provided configuration.
      $defaults = $this->propertyDefinitions->getDefaultConfig('destination', $pluginId);
      $config = array_merge($defaults, $configuration);
      $migration = $this->getStubMigration();
      return $this->getDestinationManager()->createInstance($pluginId, $config, $migration);
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Gets the plugin definition for a specific plugin.
   *
   * @param string $type
   *   The plugin type: 'source' or 'destination'.
   * @param string $pluginId
   *   The plugin ID.
   *
   * @return array|null
   *   The plugin definition or NULL if not found.
   */
  public function getPluginDefinition(string $type, string $pluginId): ?array {
    $manager = match ($type) {
      'source' => $this->getSourceManager(),
      'destination' => $this->getDestinationManager(),
      default => NULL,
    };

    if ($manager === NULL) {
      return NULL;
    }

    try {
      $definition = $manager->getDefinition($pluginId, FALSE);
      return is_array($definition) ? $definition : NULL;
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}
