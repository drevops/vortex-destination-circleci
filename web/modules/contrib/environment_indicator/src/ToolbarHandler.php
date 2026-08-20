<?php

namespace Drupal\environment_indicator;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\environment_indicator\Entity\EnvironmentIndicator as EnvironmentSwitcher;
use Drupal\environment_indicator\Service\EnvironmentIndicator;

@trigger_error('The ' . __NAMESPACE__ . '\ToolbarHandler is deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0. Instead, use \Drupal\environment_indicator\Service\EnvironmentIndicator or the environment_indicator_toolbar module. See https://www.drupal.org/node/3526893', E_USER_DEPRECATED);

/**
 * Toolbar integration handler.
 *
 * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0. Use
 * \Drupal\environment_indicator_toolbar\Service\ToolbarHandler.
 *
 * @see https://www.drupal.org/node/3526893
 */
class ToolbarHandler {

  use StringTranslationTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The environment indicator config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The active environment.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $activeEnvironment;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The state system.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected  $entityTypeManager;

  /**
   * The indicator service.
   *
   * @var \Drupal\environment_indicator\Service\EnvironmentIndicator
   */
  protected  $environmentIndicator;

  /**
   * ToolbarHandler constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\environment_indicator\Service\EnvironmentIndicator $environment_indicator
   *   The environment indicator service.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $account,
    StateInterface $state,
    Settings $settings,
    EntityTypeManagerInterface $entity_type_manager,
    EnvironmentIndicator $environment_indicator
  ) {
    $this->moduleHandler = $module_handler;
    $this->config = $config_factory->get('environment_indicator.settings');
    $this->activeEnvironment = $config_factory->get('environment_indicator.indicator');
    $this->account = $account;
    $this->state = $state;
    $this->settings = $settings;
    $this->entityTypeManager = $entity_type_manager;
    $this->environmentIndicator = $environment_indicator;
  }

  /**
   * User can access all indicators.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0. There is no
   *  direct replacement for this method.
   * @see https://www.drupal.org/node/3526812
   */
  public function hasAccessAll(): bool {
    return $this->account->hasPermission('access environment indicator');
  }

  /**
   * User can access a specific indicator.
   *
   * @param object $environment
   *   The environment identifier.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0. There is no
   *  direct replacement for this method.
   * @see https://www.drupal.org/node/3526812
   */
  public function hasAccessEnvironment($environment): bool {
    return $this->hasAccessAll() || $this->account->hasPermission('access environment indicator ' . $environment);
  }

  /**
   * User can access the indicator for the active environment.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0. There is no
   *  direct replacement for this method.
   * @see https://www.drupal.org/node/3526812
   */
  public function hasAccessActiveEnvironment(): bool {
    return $this->hasAccessEnvironment($this->activeEnvironment->get('machine'));
  }

  /**
   * Hook bridge.
   *
   * @return array
   *   The environment indicator toolbar items render array.
   *
   * @see hook_toolbar()
   */
  public function toolbar(): array {
    $items['environment_indicator'] = [
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
    // @phpstan-ignore-next-line
    if ($this->hasAccessActiveEnvironment() && $this->externalIntegration('toolbar')) {
      // @phpstan-ignore-next-line
      $title = $this->getTitle();
      $toolbar_integration = in_array('toolbar', $this->config->get('toolbar_integration') ?? [], TRUE);
      $items['environment_indicator'] += [
        '#type' => 'toolbar_item',
        '#weight' => 125,
        'tab' => [
          '#type' => 'link',
          '#title' => $title,
          '#url' => Url::fromRoute('environment_indicator.settings'),
          '#attributes' => [
            'title' => $this->t('Environments'),
            'class' => ['toolbar-icon', 'toolbar-icon-environment'],
          ],
          '#access' => !empty($title),
        ],
        'tray' => [
          '#heading' => $this->t('Environments menu'),
        ],
        '#attached' => [
          'library' => ['environment_indicator/drupal.environment_indicator'],
          'drupalSettings' => [
            'environmentIndicator' => [
              'name' => $this->activeEnvironment->get('name') ?: ' ',
              'fgColor' => $this->activeEnvironment->get('fg_color'),
              'bgColor' => $this->activeEnvironment->get('bg_color'),
              'toolbars' => $toolbar_integration,
            ],
          ],
        ],
      ];
      if ($this->config->get('favicon')) {
        $items['environment_indicator']['#attached']['drupalSettings']['environmentIndicator']['addFavicon'] = $this->config->get('favicon');
        $items['environment_indicator']['#attached']['library'][] = 'environment_indicator/favicon';
      }
      // Add cache tags to the toolbar item while preserving context.
      $items['environment_indicator']['#cache']['tags'] = Cache::mergeTags(
        [
          'config:environment_indicator.settings',
          'config:environment_indicator.indicator',
        ],
        // @phpstan-ignore-next-line
        $this->getCacheTags()
      );
      if ($this->account->hasPermission('administer environment indicator settings')) {
        $items['environment_indicator']['tray']['configuration'] = [
          '#type' => 'link',
          '#title' => $this->t('Configure'),
          '#url' => Url::fromRoute('environment_indicator.settings'),
          '#options' => [
            'attributes' => ['class' => ['edit-environments']],
          ],
        ];
      }
      // @phpstan-ignore-next-line
      if ($links = $this->getLinks()) {
        $items['environment_indicator']['tray']['environment_links'] = [
          '#theme' => 'links__toolbar_shortcuts',
          '#links' => $links,
          '#attributes' => [
            'class' => ['toolbar-menu'],
          ],
        ];
      }
    }

    return $items;
  }

  /**
   * Retrieve value from the selected version identifier source.
   *
   * @return string|null
   *   The current release identifier as a string, or NULL if not available.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0.
   *   Use
   *   \Drupal::service('environment_indicator.indicator')->getCurrentRelease()
   *   instead.
   * @see https://www.drupal.org/node/3526812
   */
  public function getCurrentRelease(): ?string {
    $version_identifier = $this->config->get('version_identifier') ?? 'environment_indicator_current_release';
    $version_identifier_fallback = $this->config->get('version_identifier_fallback') ?? 'deployment_identifier';

    $release = $this->getVersionIdentifier($version_identifier);
    if ($release !== NULL) {
      return $release;
    }

    if ($version_identifier !== $version_identifier_fallback) {
      return $this->getVersionIdentifier($version_identifier_fallback);
    }

    return NULL;
  }

  /**
   * Helper function to get version identifier based on the type.
   *
   * @param string $type
   *   The type of version identifier.
   *
   * @return string|null
   *   The version identifier.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0.
   *   Use
   *   \Drupal::service('environment_indicator.indicator')->getVersionIdentifier()
   *   instead.
   * @see https://www.drupal.org/node/3526812
   */
  protected function getVersionIdentifier(string $type): ?string {
    switch ($type) {
      case 'environment_indicator_current_release':
        $current_release = $this->state->get('environment_indicator.current_release');
        return $current_release !== NULL ? (string) $current_release : NULL;

      case 'deployment_identifier':
        $deployment_identifier = $this->settings->get('deployment_identifier');
        return $deployment_identifier !== NULL ? (string) $deployment_identifier : NULL;

      case 'drupal_version':
        return \Drupal::VERSION;

      case 'none':
      default:
        return NULL;
    }
  }

  /**
   * Construct the title for the active environment.
   *
   * @return string|null
   *   The constructed title, including the release if available, or NULL if the
   *   environment name is not set.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0.
   *   Use
   *   \Drupal::service('environment_indicator.indicator')->getTitle()
   *   instead.
   * @see https://www.drupal.org/node/3526812
   */
  public function getTitle(): ?string {
    $environment = $this->activeEnvironment->get('name');
    $release = $this->getCurrentRelease();
    return ($release) ? '(' . $release . ') ' . $environment : $environment;
  }

  /**
   * Helper function that checks if there is external integration.
   *
   * @param string $integration
   *   Name of the integration: toolbar, admin_menu, ...
   *
   * @return bool
   *   TRUE if integration is enabled. FALSE otherwise.
   */
  public function externalIntegration($integration): bool {
    if ($integration == 'toolbar') {
      if ($this->moduleHandler->moduleExists('toolbar')) {
        $toolbar_integration = $this->config->get('toolbar_integration') ?? [];
        if (in_array('toolbar', $toolbar_integration)) {
          if ($this->account->hasPermission('access toolbar')) {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the cache tags for the environment indicator switcher.
   *
   * @return array
   *   The cache tags.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0.
   *   Use
   *   \Drupal::service('environment_indicator.indicator')->getCacheTags()
   *   instead.
   * @see https://www.drupal.org/node/3526812
   */
  public function getCacheTags(): array {
    return $this->entityTypeManager->getDefinition('environment_indicator')->getListCacheTags();
  }

  /**
   * Get all the links for the switcher.
   *
   * @return array
   *   Returns all the links.
   *
   * @deprecated in environment_indicator:4.0.22 and is removed from environment_indicator:5.0.0.
   *   Use
   *   \Drupal::service('environment_indicator.indicator')->getLinks()
   *   instead.
   * @see https://www.drupal.org/node/3526812
   */
  public function getLinks(): array {
    if (!$environment_entities = EnvironmentSwitcher::loadMultiple()) {
      return [];
    }

    $current = Url::fromRoute('<current>');
    $current_path = $current->toString();
    $url = parse_url($current_path);
    $path = $url['path'];
    if (isset($url['query'])) {
      $path .= '?' . $url['query'];
    }
    $environment_entities = array_filter(
      $environment_entities,
      function (EnvironmentSwitcher $entity) {
        return $entity->status()
          && !empty($entity->getUrl())
          && $this->hasAccessEnvironment($entity->id());
      }
    );

    $links = array_map(
      function (EnvironmentSwitcher $entity) use ($path) {
        return [
          'attributes' => [
            'style' => 'color: ' . $entity->getFgColor() . '; background-color: ' . $entity->getBgColor() . ';',
            'title' => $this->t('Opens the current page in the selected environment.'),
          ],
          'title' => $this->t('Open on @label', ['@label' => $entity->label()]),
          'url' => Url::fromUri($entity->getUrl() . $path),
          'type' => 'link',
          'weight' => $entity->getWeight(),
        ];
      },
      $environment_entities
    );

    if (!$links) {
      return [];
    }

    uasort($links, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    return $links;
  }

}
