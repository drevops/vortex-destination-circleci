<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\entity_usage\Controller\ListUsageController;
use Drupal\entity_usage\EntityUsageTrackManager;
use Drupal\entity_usage\SiteDomains;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to configure entity_usage settings.
 */
class EntityUsageSettingsForm extends ConfigFormBase {
  use RedundantEditableConfigNamesTrait;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityUsageTrackManager $usageTrackManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_usage.track')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_usage_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $all_entity_types = $this->entityTypeManager->getDefinitions();
    $content_entity_types = [];

    // Filter the entity types.
    $entity_type_options = [];
    $tabs_options = [];
    $source_options = [];
    foreach ($all_entity_types as $entity_type) {
      if ($entity_type->hasKey('id')) {
        if (($entity_type instanceof ContentEntityTypeInterface)) {
          $content_entity_types[$entity_type->id()] = $entity_type->getLabel();
        }
        $entity_type_options[$entity_type->id()] = $entity_type->getLabel();
        if ($entity_type->hasLinkTemplate('canonical') || $entity_type->hasLinkTemplate('edit-form')) {
          $tabs_options[$entity_type->id()] = $entity_type->getLabel();
        }

        if ($this->usageTrackManager->isEntityTypeSource($entity_type)) {
          $source_options[$entity_type->id()] = $entity_type->getLabel();
        }
      }
    }

    natcasesort($tabs_options);
    natcasesort($entity_type_options);
    natcasesort($source_options);

    // Files and users shouldn't be tracked by default.
    unset($content_entity_types['file']);
    unset($content_entity_types['user']);

    // Inline entity types (e.g. paragraphs) are handled by their respective
    // inline entity usage tracking plugins, so they should never be configured
    // as source or target entities.
    foreach ($this->usageTrackManager->getInlineEntityTypeIds() as $inline_entity_type_id) {
      unset($content_entity_types[$inline_entity_type_id]);
      unset($entity_type_options[$inline_entity_type_id]);
      unset($source_options[$inline_entity_type_id]);
    }

    // Tabs configuration.
    $form['local_task_enabled_entity_types'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Enabled local tasks'),
      '#description' => $this->t('Check in which entity types there should be a tab (local task) linking to the usage page.'),
      '#tree' => TRUE,
    ];
    $form['local_task_enabled_entity_types']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Local task entity types'),
      '#options' => $tabs_options,
      '#config_target' => new ConfigTarget('entity_usage.settings', 'local_task_enabled_entity_types', toConfig: fn($value) => array_values(array_filter($value))),
    ];

    // Entity types (source).
    $form['track_enabled_source_entity_types'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Enabled source entity types'),
      '#description' => $this->t('Check which entity types should be tracked when source.'),
      '#tree' => TRUE,
    ];
    $form['track_enabled_source_entity_types']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Source entity types'),
      '#options' => $source_options,
      '#config_target' => new ConfigTarget(
        'entity_usage.settings',
        'track_enabled_source_entity_types',
        // If no custom settings exist, content entities are tracked by default.
        fn($value) => $value ?? array_keys($content_entity_types),
        fn($value) => array_values(array_filter($value))
      ),
      '#required' => TRUE,
    ];

    // Entity types (target).
    $form['track_enabled_target_entity_types'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Enabled target entity types'),
      '#description' => $this->t('Check which entity types should be tracked when target.'),
      '#tree' => TRUE,
    ];
    $form['track_enabled_target_entity_types']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Target entity types'),
      '#options' => $entity_type_options,
      '#config_target' => new ConfigTarget(
        'entity_usage.settings',
        'track_enabled_target_entity_types',
        // If no custom settings exist, content entities are tracked by default.
        fn($value) => $value ?? array_keys($content_entity_types),
        fn($value) => array_values(array_filter($value))
      ),
      '#required' => TRUE,
    ];

    // Plugins to enable.
    $form['track_enabled_plugins'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Enabled tracking plugins'),
      '#description' => $this->t('The following plugins were found in the system and can provide usage tracking. Check all plugins that should be active.'),
      '#tree' => TRUE,
    ];

    $plugins = $this->usageTrackManager->getDefinitions();
    $plugin_options = [];
    foreach ($plugins as $plugin) {
      $plugin_options[$plugin['id']] = $plugin['label'];
    }
    // Remove inline entity usage tracking plugins from the options — they are
    // always enabled.
    foreach ($this->usageTrackManager->getInlinePluginIds() as $inline_plugin_id) {
      unset($plugin_options[$inline_plugin_id]);
    }
    natcasesort($plugin_options);
    $form['track_enabled_plugins']['plugins'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tracking plugins'),
      '#options' => $plugin_options,
      '#config_target' => new ConfigTarget(
        'entity_usage.settings',
        'track_enabled_plugins',
        fn($value) => $value ?? array_keys($plugin_options),
        fn($value) => array_values(array_filter($value))
      ),
      '#required' => TRUE,
    ];
    // Add descriptions to all plugins that defined it.
    foreach ($plugins as $plugin) {
      if (!empty($plugin['description'])) {
        $form['track_enabled_plugins']['plugins'][$plugin['id']]['#description'] = $plugin['description'];
      }
    }

    // Edit warning message.
    $form['edit_warning_message_entity_types'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Warning message on edit form'),
      '#description' => $this->t('Check which entity types should show a message when being edited with recorded references to it.'),
      '#tree' => TRUE,
    ];
    $form['edit_warning_message_entity_types']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity types to show warning on edit form'),
      '#options' => $entity_type_options,
      '#config_target' => new ConfigTarget(
        'entity_usage.settings',
        'edit_warning_message_entity_types',
        fn($value) => $value ?? [],
        fn($value) => array_values(array_filter($value))
      ),
      '#required' => FALSE,
    ];

    // Delete warning message.
    $form['delete_warning_message_entity_types'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Warning message on delete form'),
      '#description' => $this->t('Check which entity types should show a message when being deleted with recorded references to it.'),
      '#tree' => TRUE,
    ];
    $form['delete_warning_message_entity_types']['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity types to show warning on delete form'),
      '#options' => $entity_type_options,
      '#config_target' => new ConfigTarget(
        'entity_usage.settings',
        'delete_warning_message_entity_types',
        fn($value) => $value ?? [],
        fn($value) => array_values(array_filter($value))
      ),
      '#required' => FALSE,
    ];

    // Only allow to opt-in on target entities being tracked.
    foreach (array_keys($entity_type_options) as $entity_type_id) {
      $form['edit_warning_message_entity_types']['entity_types'][$entity_type_id]['#states'] = [
        'enabled' => [
          ':input[name="track_enabled_target_entity_types[entity_types][' . $entity_type_id . ']"]' => ['checked' => TRUE],
        ],
      ];
      $form['delete_warning_message_entity_types']['entity_types'][$entity_type_id]['#states'] = [
        'enabled' => [
          ':input[name="track_enabled_target_entity_types[entity_types][' . $entity_type_id . ']"]' => ['checked' => TRUE],
        ],
      ];
    }

    // Miscellaneous settings.
    $form['generic_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Generic'),
    ];
    $form['generic_settings']['track_enabled_base_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track referencing basefields'),
      '#description' => $this->t('If enabled, relationships generated through non-configurable fields (basefields) will also be tracked.'),
      '#config_target' => 'entity_usage.settings:track_enabled_base_fields',
    ];
    $form['generic_settings']['site_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Domains for this website'),
      '#description' => $this->t("A list of domain names for this website. Absolute URLs in content are matched against these domains to identify internal links and track their usage. If Drupal is installed in a subdirectory, include the path: <em>example.com/subdir</em>. Enter one value per line, e.g. <em>example.com</em> or <em>example.com/blog</em>."),
      '#config_target' => new ConfigTarget(
        'entity_usage.settings',
        'site_domains',
        self::domainsConfigToForm(...),
        self::domainsFormToConfig(...),
      ),
    ];
    $form['generic_settings']['usage_controller_items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per page'),
      '#description' => $this->t('Define here the number of items per page that should be shown on the usage page.'),
      '#config_target' => new ConfigTarget(
        'entity_usage.settings',
        'usage_controller_items_per_page',
        fn($value) => $value ?? ListUsageController::ITEMS_PER_PAGE_DEFAULT,
      ),
      '#min' => 1,
      '#step' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback to convert config to form for the site domains config target.
   *
   * @param array|null $domains
   *   The site domains config.
   *
   * @return string
   *   The site domains config as a string.
   */
  private static function domainsConfigToForm(?array $domains): string {
    $domains = array_map(fn ($domain) => rtrim(idn_to_utf8($domain['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) . $domain['path'], '/'), $domains ?? []);
    return implode("\r\n", $domains);
  }

  /**
   * Callback to convert form to config for the site domains config target.
   *
   * @param string $domains
   *   The site domains form data.
   *
   * @return list<array{host:string, path:string}>
   *   The site domains config as an array.
   */
  private static function domainsFormToConfig(string $domains): array {
    $domains = array_values(array_filter(explode(',', preg_replace('/[\s, ]/', ',', $domains))));
    return SiteDomains::stringsToConfig($domains);
  }

}
