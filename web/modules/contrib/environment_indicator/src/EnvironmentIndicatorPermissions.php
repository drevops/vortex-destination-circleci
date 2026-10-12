<?php

namespace Drupal\environment_indicator;

use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\environment_indicator\Entity\EnvironmentIndicator;

/**
 * Provides dynamic permissions for Environment Indicators.
 */
class EnvironmentIndicatorPermissions {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * Returns the dynamic permissions array.
   *
   * @return array
   *   The permissions configuration array.
   */
  public function permissions() {
    return $this->generatePermissions(EnvironmentIndicator::loadMultiple(), [$this, 'buildPermissions']);
  }

  /**
   * Returns a list of permissions for a given environment indicator.
   *
   * @param \Drupal\environment_indicator\Entity\EnvironmentIndicator $environment
   *   The environment indicator entity.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(EnvironmentIndicator $environment): array {
    $environment_id = $environment->id();
    $environment_params = ['%environment_name' => $environment->label()];

    return [
      'access environment indicator ' . $environment_id => [
        'title' => $this->t('See environment indicator for %environment_name', $environment_params),
      ],
    ];
  }

}
