<?php

declare(strict_types=1);

namespace Drupal\deploy_steps;

use Drupal\Core\Site\Settings;

/**
 * Reads the current environment machine name.
 *
 * Opt-in capability for steps whose skip condition depends on the environment;
 * compose it with `use EnvironmentTrait;` and call ::environment().
 */
trait EnvironmentTrait {

  /**
   * Returns the current environment machine name.
   *
   * Reads $settings['environment'] (Settings::get('environment')). A site sets
   * this in settings.php; it is empty when the site does not define it.
   *
   * @return string
   *   The environment machine name (e.g. local, ci, dev, stage, prod), or an
   *   empty string when not set.
   *
   * @SuppressWarnings("PHPMD.StaticAccess")
   */
  protected function environment(): string {
    return (string) Settings::get('environment', '');
  }

}
