<?php

declare(strict_types=1);

namespace Drupal\environment_indicator\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Provides contextual info about the active environment indicator.
 */
class EnvironmentIndicator {
  use StringTranslationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Indicator constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    StateInterface $state,
    Settings $settings
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->settings = $settings;
  }

  /**
   * Gets the current release string based on version ID config and fallback.
   *
   * @return string|null
   *   The current release identifier, or NULL if not set.
   */
  public function getCurrentRelease(): ?string {
    $config = $this->configFactory->get('environment_indicator.settings');

    $primary = $config->get('version_identifier') ?? 'environment_indicator_current_release';
    $fallback = $config->get('version_identifier_fallback') ?? 'deployment_identifier';

    return $this->getVersionIdentifier($primary)
      ?? ($primary !== $fallback ? $this->getVersionIdentifier($fallback) : NULL);
  }

  /**
   * Resolves a version identifier by type.
   *
   * @param string $type
   *   The type of version identifier to retrieve.
   *
   * @return string|null
   *   The version identifier string, or NULL if not applicable.
   */
  protected function getVersionIdentifier(string $type): ?string {
    switch ($type) {
      case 'environment_indicator_current_release':
        return $this->state->get('environment_indicator.current_release');

      case 'deployment_identifier':
        return $this->settings->get('deployment_identifier');

      case 'drupal_version':
        return \Drupal::VERSION;

      default:
        return NULL;
    }
  }

  /**
   * Returns the combined label for the current environment.
   *
   * @return string|null
   *   The title string combining the environment name and current release,
   *   or NULL if no environment is set.
   */
  public function getTitle(): ?string {
    $env = $this->configFactory->get('environment_indicator.indicator');
    $environment = $env->get('name');
    $release = $this->getCurrentRelease();
    return $environment ? ($release ? "($release) $environment" : $environment) : NULL;
  }

  /**
   * Builds an array of environment switcher links.
   *
   * @return array[]
   *   A render array of link definitions for each active environment.
   */
  public function getLinks(): array {
    /** @var \Drupal\environment_indicator\Entity\EnvironmentIndicator[] $entities */
    $entities = $this->entityTypeManager->getStorage('environment_indicator')->loadMultiple();
    $current = Url::fromRoute('<current>');
    $current_path = $current->toString();
    $url = parse_url($current_path);
    $path = $url['path'];
    if (isset($url['query'])) {
      $path .= '?' . $url['query'];
    }
    $links = [];
    foreach ($entities as $entity) {
      if (!$entity->status() || empty($entity->getUrl())) {
        continue;
      }
      $links[] = [
        'attributes' => [
          'style' => sprintf('color: %s; background-color: %s;', $entity->getFgColor(), $entity->getBgColor()),
          'title' => $this->t('Opens the current page in the selected environment.'),
        ],
        'title' => $this->t('Open on @label', ['@label' => $entity->label()]),
        'url' => Url::fromUri($entity->getUrl() . $path),
        'type' => 'link',
        'weight' => $entity->getWeight(),
      ];
    }
    uasort($links, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

    return $links;
  }

  /**
   * Returns cache tags related to the switcher list.
   *
   * @return string[]
   *   An array of cache tags.
   */
  public function getCacheTags(): array {
    return $this->entityTypeManager
      ->getDefinition('environment_indicator')
      ->getListCacheTags();
  }

}
