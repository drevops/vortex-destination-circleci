<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Service providing plugin property definitions from YAML configuration.
 *
 * Since migrate plugins don't implement PluginFormInterface, this service
 * loads property definitions from YAML files to enable configuration forms
 * in the Modeler API.
 */
final class PluginPropertyDefinitions {

  use StringTranslationTrait;

  /**
   * Cached property definitions.
   *
   * @var array|null
   */
  protected ?array $definitions = NULL;

  /**
   * Constructs the PluginPropertyDefinitions service.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Loads all plugin property definitions from YAML files.
   *
   * @return array
   *   The complete property definitions array.
   */
  protected function loadDefinitions(): array {
    if ($this->definitions !== NULL) {
      return $this->definitions;
    }

    $this->definitions = [
      'source' => [],
      'process' => [],
      'destination' => [],
    ];

    // Load from all modules that provide modeler_api.plugin_properties.yml.
    foreach ($this->moduleExtensionList->getAllInstalledInfo() as $module => $info) {
      $path = $this->moduleExtensionList->getPath($module);
      $file = $path . '/config/schema/' . $module . '.modeler_api.plugin_properties.yml';
      if (file_exists($file)) {
        $content = file_get_contents($file);
        if ($content !== FALSE) {
          $data = Yaml::decode($content);
          if (is_array($data) && isset($data['migrate_plus']['plugins'])) {
            foreach (['source', 'process', 'destination'] as $type) {
              if (isset($data['migrate_plus']['plugins'][$type])) {
                $this->definitions[$type] = array_merge(
                  $this->definitions[$type],
                  $data['migrate_plus']['plugins'][$type]
                );
              }
            }
          }
        }
      }
    }

    return $this->definitions;
  }

  /**
   * Gets the property definitions for a specific plugin.
   *
   * @param string $type
   *   The plugin type: 'source', 'process', or 'destination'.
   * @param string $pluginId
   *   The plugin ID.
   *
   * @return array
   *   The property definitions array, or empty array if not defined.
   */
  public function getPropertiesForPlugin(string $type, string $pluginId): array {
    $definitions = $this->loadDefinitions();

    // First try exact match.
    if (isset($definitions[$type][$pluginId]['properties'])) {
      return $definitions[$type][$pluginId]['properties'];
    }

    // For derived plugins (e.g., 'content_entity:node', 'entity:node'),
    // try the base plugin ID (before the colon).
    if (str_contains($pluginId, ':')) {
      $basePluginId = explode(':', $pluginId, 2)[0];
      if (isset($definitions[$type][$basePluginId]['properties'])) {
        return $definitions[$type][$basePluginId]['properties'];
      }
    }

    return [];
  }

  /**
   * Checks if a plugin has property definitions.
   *
   * @param string $type
   *   The plugin type: 'source', 'process', or 'destination'.
   * @param string $pluginId
   *   The plugin ID.
   *
   * @return bool
   *   TRUE if the plugin has property definitions.
   */
  public function hasProperties(string $type, string $pluginId): bool {
    return !empty($this->getPropertiesForPlugin($type, $pluginId));
  }

  /**
   * Builds a configuration form for a plugin based on its property definitions.
   *
   * @param string $type
   *   The plugin type: 'source', 'process', or 'destination'.
   * @param string $pluginId
   *   The plugin ID.
   * @param array $config
   *   The current configuration values.
   *
   * @return array
   *   A Drupal form render array.
   */
  public function buildFormForPlugin(string $type, string $pluginId, array $config = []): array {
    $form = [];
    $properties = $this->getPropertiesForPlugin($type, $pluginId);

    foreach ($properties as $key => $definition) {
      $form[$key] = $this->buildFormElement($key, $definition, $config[$key] ?? NULL);
    }

    return $form;
  }

  /**
   * Builds a single form element from a property definition.
   *
   * @param string $key
   *   The property key.
   * @param array $definition
   *   The property definition.
   * @param mixed $value
   *   The current value.
   *
   * @return array
   *   A form element render array.
   */
  protected function buildFormElement(string $key, array $definition, mixed $value): array {
    $type = $definition['type'] ?? 'string';
    $element = [
      '#title' => $this->translateLabel($definition['label'] ?? $key),
      '#description' => isset($definition['description']) ? $this->translateLabel($definition['description']) : '',
      '#required' => $definition['required'] ?? FALSE,
    ];

    // Set default value.
    if ($value !== NULL) {
      $element['#default_value'] = $value;
    }
    elseif (isset($definition['default'])) {
      $element['#default_value'] = $definition['default'];
    }

    // Map type to form element type.
    switch ($type) {
      case 'string':
        $element['#type'] = 'textfield';
        if (isset($definition['maxlength'])) {
          $element['#maxlength'] = $definition['maxlength'];
        }
        break;

      case 'text':
        $element['#type'] = 'textarea';
        if (isset($definition['rows'])) {
          $element['#rows'] = $definition['rows'];
        }
        if (isset($element['#default_value']) && !is_scalar($element['#default_value'])) {
          $element['#default_value'] = Yaml::encode($element['#default_value']);
        }
        break;

      case 'integer':
      case 'number':
        $element['#type'] = 'number';
        if (isset($definition['min'])) {
          $element['#min'] = $definition['min'];
        }
        if (isset($definition['max'])) {
          $element['#max'] = $definition['max'];
        }
        if (isset($definition['step'])) {
          $element['#step'] = $definition['step'];
        }
        break;

      case 'boolean':
        $element['#type'] = 'checkbox';
        break;

      case 'select':
        $element['#type'] = 'select';
        $element['#options'] = $definition['options'] ?? [];
        if (!empty($definition['empty_option'])) {
          $element['#empty_option'] = $this->translateLabel($definition['empty_option']);
        }
        break;

      case 'array':
        // For arrays, use textarea with each line as an item.
        $element['#type'] = 'textarea';
        $element['#description'] = ($element['#description'] ? $element['#description'] . ' ' : '') .
          $this->t('Enter one item per line.');
        if (is_array($value)) {
          $element['#default_value'] = implode("\n", $value);
        }
        break;

      case 'mapping':
        // For key-value mappings, use textarea with key|value format.
        $element['#type'] = 'textarea';
        $element['#description'] = ($element['#description'] ? $element['#description'] . ' ' : '') .
          $this->t('Enter one item per line in the format: key|value');
        if (is_array($value)) {
          $lines = [];
          foreach ($value as $k => $v) {
            $lines[] = $k . '|' . $v;
          }
          $element['#default_value'] = implode("\n", $lines);
        }
        break;

      case 'entity_type_select':
        $element['#type'] = 'select';
        $element['#options'] = $this->getEntityTypeOptions();
        $element['#empty_option'] = $this->t('- Select entity type -');
        break;

      case 'bundle_select':
        // This needs dynamic handling based on entity type.
        $element['#type'] = 'textfield';
        $element['#description'] = ($element['#description'] ? $element['#description'] . ' ' : '') .
          $this->t('Enter the bundle machine name.');
        break;

      default:
        $element['#type'] = 'textfield';
        break;
    }

    return $element;
  }

  /**
   * Translates a label string.
   *
   * @param string|TranslatableMarkup $label
   *   The label to translate.
   *
   * @return string|TranslatableMarkup
   *   The translated label.
   */
  protected function translateLabel(string|TranslatableMarkup $label): string|TranslatableMarkup {
    if ($label instanceof TranslatableMarkup) {
      return $label;
    }
    return $this->t('@label', ['@label' => $label]);
  }

  /**
   * Gets entity type options for select elements.
   *
   * @return array
   *   An array of entity type labels keyed by entity type ID.
   */
  protected function getEntityTypeOptions(): array {
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      if ($definition->getGroup() === 'content') {
        $options[$id] = $definition->getLabel();
      }
    }
    asort($options);
    return $options;
  }

  /**
   * Processes submitted form values back to configuration format.
   *
   * @param string $type
   *   The plugin type: 'source', 'process', or 'destination'.
   * @param string $pluginId
   *   The plugin ID.
   * @param array $values
   *   The submitted form values.
   *
   * @return array
   *   The processed configuration array.
   */
  public function processFormValues(string $type, string $pluginId, array $values): array {
    $properties = $this->getPropertiesForPlugin($type, $pluginId);
    $config = [];

    foreach ($properties as $key => $definition) {
      if (!isset($values[$key])) {
        continue;
      }

      $value = $values[$key];
      $propType = $definition['type'] ?? 'string';

      switch ($propType) {
        case 'array':
          // Convert textarea lines to array.
          if (is_string($value)) {
            $value = array_filter(array_map('trim', explode("\n", $value)));
          }
          break;

        case 'mapping':
          // Convert key|value lines to associative array.
          if (is_string($value)) {
            $lines = array_filter(array_map('trim', explode("\n", $value)));
            $value = [];
            foreach ($lines as $line) {
              if (str_contains($line, '|')) {
                [$k, $v] = explode('|', $line, 2);
                $value[trim($k)] = trim($v);
              }
            }
          }
          break;

        case 'integer':
        case 'number':
          $value = is_numeric($value) ? (int) $value : NULL;
          break;

        case 'boolean':
          $value = (bool) $value;
          break;
      }

      if ($value !== NULL && $value !== '' && $value !== []) {
        $config[$key] = $value;
      }
    }

    return $config;
  }

  /**
   * Clears the cached definitions.
   */
  public function clearCache(): void {
    $this->definitions = NULL;
  }

  /**
   * Gets the default configuration for a plugin.
   *
   * Builds a configuration array with default values for all properties
   * that have defaults defined. This is useful for instantiating plugins
   * that require certain configuration to be present.
   *
   * @param string $type
   *   The plugin type: 'source', 'process', or 'destination'.
   * @param string $pluginId
   *   The plugin ID.
   *
   * @return array
   *   The default configuration array.
   */
  public function getDefaultConfig(string $type, string $pluginId): array {
    $properties = $this->getPropertiesForPlugin($type, $pluginId);
    $defaults = [];

    foreach ($properties as $key => $definition) {
      if (isset($definition['default'])) {
        $defaults[$key] = $definition['default'];
      }
    }

    return $defaults;
  }

}
