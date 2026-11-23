<?php

declare(strict_types=1);

namespace Drupal\entity_usage_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for entity_usage_test.
 */
class EntityUsageTestHooks {

  /**
   * Implements hook_entity_usage_block_tracking().
   */
  #[Hook('entity_usage_block_tracking')]
  public function entityUsageBlockTracking(string $target_id, string $target_type, string $source_id, string $source_type, string $source_langcode, string $source_vid, string $method, string $field_name, string $count): bool {
    if ($count == 31) {
      return TRUE;
    }
    return FALSE;
  }

}
