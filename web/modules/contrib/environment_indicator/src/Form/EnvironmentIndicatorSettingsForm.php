<?php

namespace Drupal\environment_indicator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure System settings for this site.
 */
class EnvironmentIndicatorSettingsForm extends ConfigFormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a MenuLinksetSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      $config_factory,
      $typedConfigManager
    );
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'environment_indicator_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('environment_indicator.settings');
    $toolbar_integration_module_enabled = $this->moduleHandler->moduleExists('environment_indicator_toolbar') ?? FALSE;
    $toolbar_integration_setting_enabled = !empty($config->get('toolbar_integration'));
    $form = parent::buildForm($form, $form_state);
    $form['toolbar_integration'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Toolbar integration (Deprecated)'),
      '#options' => [
        'toolbar' => $this->t('Toolbar (Deprecated)'),
      ],
      '#description' => $this->t('This setting is deprecated and will be removed in a future release. Please enable the <strong>Environment Indicator - Toolbar Integration</strong> module to remove this setting early.'),
      '#default_value' => $config->get('toolbar_integration') ?: [],
    ];
    // If the toolbar integration settings are empty, we remove the
    // toolbar integration settings from the form.
    if (!$toolbar_integration_setting_enabled) {
      unset($form['toolbar_integration']);
    }
    // If the toolbar integration module is not enabled, we add a message to
    // inform the user.
    if (!$toolbar_integration_module_enabled) {
      $form['toolbar_integration_message'] = [
        '#type' => 'item',
        '#markup' => $this->t('The <strong>Environment Indicator - Toolbar Integration</strong> (<code>environment_indicator_toolbar</code>) module is not enabled. Please enable it to enable toolbar integration.'),
      ];
    }
    $form['favicon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show favicon'),
      '#description' => $this->t('If checked, a favicon will be added with the environment colors when the indicator is shown.'),
      '#default_value' => $config->get('favicon') ?: FALSE,
    ];

    $version_identifier_options = [
      'environment_indicator_current_release' => $this->t('Environment Indicator Current Release'),
      'deployment_identifier' => $this->t('Deployment Identifier'),
      'drupal_version' => $this->t('Drupal Version'),
      'none' => $this->t('None'),
    ];

    $form['version_identifier'] = [
      '#type' => 'select',
      '#title' => $this->t('Source of version identifier to display'),
      '#description' => $this->t('Select the source of the version identifier to display in the environment indicator.'),
      '#options' => $version_identifier_options,
      '#default_value' => $config->get('version_identifier') ?: 'deployment_identifier',
      '#ajax' => [
        'callback' => '::updateFallbackOptions',
        'event' => 'change',
        'wrapper' => 'version-identifier-fallback-wrapper',
      ],
    ];

    $version_identifier = $form_state->getValue('version_identifier', $config->get('version_identifier') ?: 'deployment_identifier');

    if ($version_identifier === 'none') {
      $fallback_options = ['none' => $this->t('None')];
    }
    else {
      $fallback_options = array_diff_key($version_identifier_options, [$version_identifier => '']);
    }

    $form['version_identifier_fallback'] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback source of version identifier to display'),
      '#description' => $this->t('Select the fallback source of the version identifier to display in the environment indicator.'),
      '#options' => $fallback_options,
      '#default_value' => $config->get('version_identifier_fallback') ?: 'none',
      '#prefix' => '<div id="version-identifier-fallback-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="version_identifier"]' => ['!value' => 'none'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback to update the fallback options.
   */
  public function updateFallbackOptions(array &$form, FormStateInterface $form_state) {
    return $form['version_identifier_fallback'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['environment_indicator.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('environment_indicator.settings');

    $config
      ->set('favicon', $form_state->getValue('favicon'))
      ->set('version_identifier', $form_state->getValue('version_identifier'))
      ->set('version_identifier_fallback', $form_state->getValue('version_identifier_fallback'))
      ->save();

    if (isset($form['toolbar_integration'])) {
      $config->set('toolbar_integration', array_filter($form_state->getValue('toolbar_integration')))
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
