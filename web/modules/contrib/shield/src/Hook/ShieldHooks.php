<?php

namespace Drupal\shield\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for shield.
 */
class ShieldHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the shield module.
      case 'help.page.shield':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('It creates a simple shield for the site with HTTP
            basic authentication. It hides the sites, if the user does not know a simple
        username/password.') . '</p>';
        return $output;

      default:
    }
  }

}
