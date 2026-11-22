<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\ModelerApiModelOwner;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Service\MigratePlusPlugins;
use Drupal\migrate_plus\Service\PluginPropertyDefinitions;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\ModelOwner;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Model owner plugin implementation for Migrate Plus.
 *
 * This plugin integrates Drupal migrations with the Modeler API,
 * allowing visual design of migration configurations.
 *
 * Component mapping:
 * - Start (COMPONENT_TYPE_START): Source plugins - data sources
 * - Element (COMPONENT_TYPE_ELEMENT): Destination plugins - where data goes
 * - Gateway (COMPONENT_TYPE_GATEWAY): Field mappings - each gateway represents
 *   a destination field with its process plugin chain configured via YAML
 */
#[ModelOwner(
  id: "migrate_plus",
  label: new TranslatableMarkup("Migrations"),
  description: new TranslatableMarkup("Configure data migrations with source, process, and destination plugins."),
  uiLabelNewModel: new TranslatableMarkup("Add new migration"),
  uiLabelNewModelWithModeler: new TranslatableMarkup("Add new migration with modeler"),
)]
final class MigratePlus extends ModelOwnerBase {

  /**
   * Component type mapping for migrations.
   *
   * - Start: Source plugins (one per migration).
   * - Element: Destination plugins (one per migration).
   * - Gateway: Field mappings with process configuration.
   */
  public const SUPPORTED_COMPONENT_TYPES = [
    Api::COMPONENT_TYPE_START => 'source',
    Api::COMPONENT_TYPE_ELEMENT => 'destination',
    Api::COMPONENT_TYPE_GATEWAY => 'field',
  ];

  /**
   * The virtual plugin ID for field gateways.
   */
  public const FIELD_PLUGIN_ID = 'migrate_plus_field';

  /**
   * The container for lazy service loading.
   */
  protected ?ContainerInterface $container = NULL;

  /**
   * The migrate plus plugins service.
   */
  protected ?MigratePlusPlugins $pluginsService = NULL;

  /**
   * The plugin property definitions service.
   */
  protected ?PluginPropertyDefinitions $propertyDefinitions = NULL;

  /**
   * {@inheritdoc}
   */
  public function componentLabels(): array {
    return [
      'start' => (string) $this->t('Source'),
      'element' => (string) $this->t('Destination'),
      'gateway' => (string) $this->t('Field'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function componentLabelsPlural(): array {
    return [
      'start' => (string) $this->t('Sources'),
      'element' => (string) $this->t('Destinations'),
      'gateway' => (string) $this->t('Fields'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function modelConstraints(): array {
    return [
      Api::COMPONENT_TYPE_START => [
        'min' => 1,
        'max' => 1,
        'successors' => ['min' => 1, 'max' => 1],
      ],
      Api::COMPONENT_TYPE_ELEMENT => [
        'min' => 1,
        'max' => 1,
        'successors' => ['max' => 0],
      ],
      Api::COMPONENT_TYPE_GATEWAY => [
        'successors' => ['min' => 1, 'max' => 1],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function modelIdExistsCallback(): array {
    return [Migration::class, 'load'];
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityProviderId(): string {
    return 'migrate_plus';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityTypeId(): string {
    return 'migration';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityBasePath(): string {
    return 'admin/structure/migrations';
  }

  /**
   * Gets the DI container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container.
   */
  protected function getContainer(): ContainerInterface {
    if ($this->container === NULL) {
      // @phpstan-ignore-next-line
      $this->container = \Drupal::getContainer();
    }
    return $this->container;
  }

  /**
   * Gets the migrate plus plugins service.
   *
   * @return \Drupal\migrate_plus\Service\MigratePlusPlugins
   *   The plugins service.
   */
  protected function getPluginsService(): MigratePlusPlugins {
    if ($this->pluginsService === NULL) {
      $this->pluginsService = $this->getContainer()->get('migrate_plus.plugins');
    }
    return $this->pluginsService;
  }

  /**
   * Gets the plugin property definitions service.
   *
   * @return \Drupal\migrate_plus\Service\PluginPropertyDefinitions
   *   The property definitions service.
   */
  protected function getPropertyDefinitions(): PluginPropertyDefinitions {
    if ($this->propertyDefinitions === NULL) {
      $this->propertyDefinitions = $this->getContainer()->get('migrate_plus.plugin_property_definitions');
    }
    return $this->propertyDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function supportedOwnerComponentTypes(): array {
    return self::SUPPORTED_COMPONENT_TYPES;
  }

  /**
   * {@inheritdoc}
   */
  public function availableOwnerComponents(int $type): array {
    return match ($type) {
      Api::COMPONENT_TYPE_START => $this->getPluginsService()->getSources(),
      Api::COMPONENT_TYPE_ELEMENT => $this->getPluginsService()->getDestinations(),
      Api::COMPONENT_TYPE_GATEWAY => $this->getFieldPluginInstances(),
      default => [],
    };
  }

  /**
   * Gets the virtual field plugin instances.
   *
   * Field mappings are represented as a single virtual plugin type.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface[]
   *   Array containing the field plugin instance.
   */
  protected function getFieldPluginInstances(): array {
    return [$this->createFieldPluginInstance()];
  }

  /**
   * Creates a virtual field plugin instance.
   *
   * @param array $configuration
   *   Optional configuration.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface
   *   The field plugin instance.
   */
  protected function createFieldPluginInstance(array $configuration = []): PluginInspectionInterface {
    return new MigrateFieldPlugin(self::FIELD_PLUGIN_ID, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentId(int $type): string {
    return self::SUPPORTED_COMPONENT_TYPES[$type] ?? 'unsupported';
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponent(int $type, string $id, array $config = []): ?PluginInspectionInterface {
    return match ($type) {
      Api::COMPONENT_TYPE_START => $this->getPluginsService()->createSourceInstance($id, $config),
      Api::COMPONENT_TYPE_ELEMENT => $this->getPluginsService()->createDestinationInstance($id, $config),
      Api::COMPONENT_TYPE_GATEWAY => $this->createFieldPluginInstance($config),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponents(ConfigEntityInterface $model): array {
    assert($model instanceof Migration);
    $components = [];

    // Collect field mapping component IDs in order so we can build a chain:
    // source → field_1 → field_2 → ... → field_n → destination.
    $fieldComponentIds = [];
    $process = $model->get('process');
    if (is_array($process)) {
      foreach (array_keys($process) as $destinationField) {
        $fieldComponentIds[] = 'field_' . $this->sanitizeComponentId((string) $destinationField);
      }
    }

    // Source → first field (or destination if no fields).
    $source = $model->get('source');
    if (is_array($source) && !empty($source['plugin'])) {
      $sourceConfig = $source;
      $pluginId = $sourceConfig['plugin'];
      unset($sourceConfig['plugin']);
      $firstSuccessor = !empty($fieldComponentIds) ? $fieldComponentIds[0] : 'destination';
      $components[] = new Component(
        $this,
        'source',
        Api::COMPONENT_TYPE_START,
        $pluginId,
        (string) $this->t('Source: @plugin', ['@plugin' => $pluginId]),
        $sourceConfig,
        [new ComponentSuccessor($firstSuccessor, '')],
      );
    }

    // Destination has no successors.
    $destination = $model->get('destination');
    if (is_array($destination) && !empty($destination['plugin'])) {
      $destConfig = $destination;
      $pluginId = $destConfig['plugin'];
      unset($destConfig['plugin']);
      $components[] = new Component(
        $this,
        'destination',
        Api::COMPONENT_TYPE_ELEMENT,
        $pluginId,
        (string) $this->t('Destination: @plugin', ['@plugin' => $pluginId]),
        $destConfig,
        [],
      );
    }

    // Field mappings chained: field_1 → field_2 → ... → field_n → destination.
    if (is_array($process)) {
      $fieldIndex = 0;
      $fieldCount = count($fieldComponentIds);
      foreach ($process as $destinationField => $processConfig) {
        $processYaml = $this->processConfigToYaml($processConfig, (string) $destinationField);
        $componentId = $fieldComponentIds[$fieldIndex];
        // Each field points to the next field, or to destination if it's last.
        $nextId = ($fieldIndex + 1 < $fieldCount) ? $fieldComponentIds[$fieldIndex + 1] : 'destination';
        $components[] = new Component(
          $this,
          $componentId,
          Api::COMPONENT_TYPE_GATEWAY,
          self::FIELD_PLUGIN_ID,
          (string) $destinationField,
          [
            'field_name' => $destinationField,
            'process' => $processYaml,
          ],
          [new ComponentSuccessor($nextId, '')],
        );
        $fieldIndex++;
      }
    }

    return $components;
  }

  /**
   * Converts process configuration to a normalized YAML pipeline string.
   *
   * Migrate's process config supports several shorthand notations that are
   * expanded here so the modeler always displays an explicit pipeline:
   *
   *   - Simple string ("password") → get plugin with source: password
   *   - "@field" reference → get plugin with source: @field
   *   - Associative array (single plugin without source) → source injected
   *     from the destination field name
   *   - Sequential array (pipeline) → kept as-is
   *
   * @param mixed $processConfig
   *   The process configuration for a single destination field.
   * @param string $destinationField
   *   The destination field name, used as implicit source when the plugin
   *   config does not specify one.
   *
   * @return string
   *   YAML representation of the normalized process pipeline.
   */
  protected function processConfigToYaml(mixed $processConfig, string $destinationField): string {
    if (is_string($processConfig)) {
      // Simple field mapping (e.g., "password" or "@created") is shorthand
      // for the get plugin.
      return Yaml::encode([['plugin' => 'get', 'source' => $processConfig]]);
    }
    if (is_array($processConfig)) {
      // Normalize to a sequential pipeline array.
      if (!array_is_list($processConfig)) {
        // Single plugin config (associative array) → wrap in a list.
        $processConfig = [$processConfig];
      }
      // Inject implicit source into the first plugin if missing. Migrate
      // uses the destination field name as the default source.
      if (!empty($processConfig) && !isset($processConfig[0]['source'])) {
        $processConfig[0]['source'] = $destinationField;
      }
      return Yaml::encode($processConfig);
    }
    return '';
  }

  /**
   * Parses YAML process configuration.
   *
   * @param string $yaml
   *   The YAML string.
   *
   * @return mixed
   *   The parsed process configuration.
   */
  protected function parseProcessYaml(string $yaml): mixed {
    $yaml = trim($yaml);
    if ($yaml === '') {
      return [];
    }

    // Check if it's a simple field name (no YAML structure).
    if (!str_contains($yaml, ':') && !str_contains($yaml, "\n") && !str_contains($yaml, '-')) {
      return $yaml;
    }

    try {
      return Yaml::decode($yaml);
    }
    catch (\Exception) {
      return $yaml;
    }
  }

  /**
   * Sanitizes a field name for use as component ID.
   *
   * @param string $fieldName
   *   The field name.
   *
   * @return string
   *   A sanitized component ID.
   */
  protected function sanitizeComponentId(string $fieldName): string {
    return preg_replace('/[^a-z0-9_]/', '_', strtolower($fieldName));
  }

  /**
   * {@inheritdoc}
   */
  public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface {
    assert($model instanceof Migration);
    $model->set('source', ['plugin' => 'empty']);
    $model->set('process', []);
    $model->set('destination', ['plugin' => 'null']);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addComponent(ConfigEntityInterface $model, Component $component): bool {
    assert($model instanceof Migration);

    $type = $component->getType();
    $pluginId = $component->getPluginId();
    $configuration = $component->getConfiguration();

    switch ($type) {
      case Api::COMPONENT_TYPE_START:
        // Source plugin.
        $sourceConfig = array_merge(['plugin' => $pluginId], $configuration);
        $model->set('source', $sourceConfig);
        return TRUE;

      case Api::COMPONENT_TYPE_ELEMENT:
        // Destination plugin.
        $destConfig = array_merge(['plugin' => $pluginId], $configuration);
        $model->set('destination', $destConfig);
        return TRUE;

      case Api::COMPONENT_TYPE_GATEWAY:
        // Field mapping.
        $fieldName = $configuration['field_name'] ?? '';
        if (empty($fieldName)) {
          return FALSE;
        }

        $processYaml = $configuration['process'] ?? '';
        $processConfig = $this->parseProcessYaml($processYaml);

        $process = $model->get('process') ?? [];
        $process[$fieldName] = $processConfig;
        $model->set('process', $process);
        return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponent(ConfigEntityInterface $model, Component $component): bool {
    return $this->addComponent($model, $component);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId = NULL, bool $modelIsNew = TRUE): array {
    $pluginId = $plugin->getPluginId();

    // Handle field gateway configuration.
    if ($pluginId === self::FIELD_PLUGIN_ID) {
      return $this->buildFieldConfigurationForm($plugin);
    }

    // Determine plugin type from the plugin class.
    $type = $this->determinePluginType($plugin);
    if ($type === NULL) {
      return [];
    }

    // Get current configuration.
    $configuration = [];
    if ($plugin instanceof ConfigurableInterface) {
      $configuration = $plugin->getConfiguration();
    }

    // Build form from property definitions.
    $form = $this->getPropertyDefinitions()->buildFormForPlugin($type, $pluginId, $configuration);

    // If no property definitions exist, show a message.
    if (empty($form)) {
      $form['info'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No configuration options are defined for this plugin. You can add configuration manually in the YAML.') . '</p>',
      ];
    }

    return $form;
  }

  /**
   * Builds the configuration form for field gateways.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The field plugin instance.
   *
   * @return array
   *   The form array.
   */
  protected function buildFieldConfigurationForm(PluginInspectionInterface $plugin): array {
    $configuration = [];
    if ($plugin instanceof ConfigurableInterface) {
      $configuration = $plugin->getConfiguration();
    }

    $form = [];

    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The destination field name (e.g., "title", "field_body", "uid").'),
      '#default_value' => $configuration['field_name'] ?? '',
      '#required' => TRUE,
      '#weight' => -10,
    ];

    $form['process'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Process plugins'),
      '#description' => $this->t('Define the process plugin chain in YAML format. For a simple field mapping, just enter the source field name. For complex transformations, use the full process plugin configuration.<br><br><strong>Examples:</strong><br><code>source_field</code> - Simple mapping from source field<br><code>plugin: default_value<br>default_value: "My default"</code> - Single plugin<br><code>- plugin: get<br>  source: title<br>- plugin: callback<br>  callable: strtolower</code> - Plugin chain'),
      '#default_value' => $configuration['process'] ?? '',
      '#rows' => 10,
      '#weight' => 0,
    ];

    return $form;
  }

  /**
   * Determines the plugin type from a plugin instance.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin instance.
   *
   * @return string|null
   *   The plugin type ('source', 'destination') or NULL.
   */
  protected function determinePluginType(PluginInspectionInterface $plugin): ?string {
    if ($plugin->getPluginId() === self::FIELD_PLUGIN_ID) {
      return 'field';
    }

    $class = get_class($plugin);

    if (is_subclass_of($class, 'Drupal\migrate\Plugin\MigrateSourceInterface')) {
      return 'source';
    }
    if (is_subclass_of($class, 'Drupal\migrate\Plugin\MigrateDestinationInterface')) {
      return 'destination';
    }

    // Fallback: check via plugin definition.
    $pluginId = $plugin->getPluginId();
    if ($this->getPluginsService()->getPluginDefinition('source', $pluginId)) {
      return 'source';
    }
    if ($this->getPluginsService()->getPluginDefinition('destination', $pluginId)) {
      return 'destination';
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentDefaultConfig(int $type, string $id): array {
    if ($type === Api::COMPONENT_TYPE_GATEWAY) {
      return [
        'field_name' => '',
        'process' => '',
      ];
    }

    $typeString = match ($type) {
      Api::COMPONENT_TYPE_START => 'source',
      Api::COMPONENT_TYPE_ELEMENT => 'destination',
      default => NULL,
    };

    if ($typeString === NULL) {
      return [];
    }

    // Return default values from property definitions.
    $properties = $this->getPropertyDefinitions()->getPropertiesForPlugin($typeString, $id);
    $defaults = [];
    foreach ($properties as $key => $definition) {
      if (isset($definition['default'])) {
        $defaults[$key] = $definition['default'];
      }
    }
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSchemaKey(PluginInspectionInterface $plugin): string {
    $pluginId = $plugin->getPluginId();

    if ($pluginId === self::FIELD_PLUGIN_ID) {
      return 'migrate_plus.gateway.field';
    }

    $type = $this->determinePluginType($plugin);

    return match ($type) {
      'source' => 'migrate.source.' . $pluginId,
      'destination' => 'migrate.destination.' . $pluginId,
      default => '',
    };
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeAddingComponents(ConfigEntityInterface $model): void {
    assert($model instanceof Migration);
    // Ensure required fields have proper defaults for schema compliance.
    // New entities start with NULL for these fields which violates the
    // config schema that expects a sequence and a mapping respectively.
    if ($model->get('migration_tags') === NULL) {
      $model->set('migration_tags', []);
    }
    if ($model->get('migration_dependencies') === NULL) {
      $model->set('migration_dependencies', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponentsInfo(ConfigEntityInterface $model): array {
    assert($model instanceof Migration);

    $info = [];
    $source = $model->get('source');
    if (is_array($source) && !empty($source['plugin'])) {
      $info['source'] = [
        'plugin' => $source['plugin'],
        'status' => TRUE,
      ];
    }

    $destination = $model->get('destination');
    if (is_array($destination) && !empty($destination['plugin'])) {
      $info['destination'] = [
        'plugin' => $destination['plugin'],
        'status' => TRUE,
      ];
    }

    $process = $model->get('process');
    if (is_array($process)) {
      $info['field_count'] = count($process);
    }

    return $info;
  }

}
