<?php

declare(strict_types=1);

namespace Drupal\sdc_devel\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for sdc_devel.
 */
class SdcDevelHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'status_report_errors' => [
        'variables' => [
          'amount' => NULL,
          'text' => NULL,
          'severity' => NULL,
        ],
      ],
    ];
  }

}
