<?php

declare(strict_types=1);

namespace Drupal\entity_usage;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the possible source entity statuses.
 */
enum SourceEntityStatus {
  // A publishable entity where the default revision is published.
  case Published;
  // A publishable entity where the default revision is unpublished.
  case Unpublished;
  // A non-publishable entity.
  case Current;

  /**
   * Gets the label for the source entity status.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label for the source entity status.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::Published => new TranslatableMarkup('Published revision'),
      self::Unpublished => new TranslatableMarkup('Unpublished revision'),
      self::Current => new TranslatableMarkup('Current revision'),
    };
  }

}
