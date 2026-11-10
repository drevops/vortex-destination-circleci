<?php

declare(strict_types=1);

namespace Drupal\entity_usage;

/**
 * Interface for track plugins that handle inline entities.
 *
 * Inline entities are entities (like paragraphs) that should not appear
 * directly in the entity_usage table as sources or targets. Instead, an inline
 * entity usage tracking plugin looks through them and records the parent
 * non-inline entity as the source.
 *
 * Any track plugin implementing this interface will:
 * - Always be enabled (regardless of the "track_enabled_plugins" config).
 * - Have its inline entity types excluded from the settings form options for
 *   source/target entity type tracking.
 * - Cause its inline entity types to be skipped in allowSourceEntityTracking()
 *   and allowTargetEntityTracking().
 */
interface EntityUsageInlineTrackingInterface extends EntityUsageTrackInterface {

  /**
   * Returns entity type IDs that this plugin always treats as inline.
   *
   * @return string[]
   *   An array of entity type IDs (e.g. ['paragraph']).
   */
  public function getInlineEntityTypeIds(): array;

}
