<?php

declare(strict_types=1);

namespace Drupal\deploy_steps;

/**
 * Reads environment variables.
 *
 * Opt-in capability for steps configured by environment variables the deploy
 * pipeline exports; compose it with `use EnvTrait;` and call ::env(). For the
 * Drupal environment marker (local, prod, ...) read from settings.php instead,
 * use \Drupal\deploy_steps\EnvironmentTrait.
 */
trait EnvTrait {

  /**
   * Reads an environment variable, falling back to a default.
   *
   * @param string $name
   *   The environment variable name.
   * @param string $default
   *   The value to return when the variable is unset.
   *
   * @return string
   *   The environment variable value, or the default when it is unset.
   */
  protected function env(string $name, string $default = ''): string {
    $value = getenv($name);

    return $value === FALSE ? $default : $value;
  }

}
