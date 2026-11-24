<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

final class ScheduledTransitionsTokenReplacementFactory implements ScheduledTransitionsTokenReplacementFactoryInterface {

  public function __construct(
    private ModerationInformationInterface $moderationInformation,
  ) {
  }

  public function create(
    ScheduledTransitionInterface $scheduledTransition,
    RevisionableInterface $newRevision,
    RevisionableInterface $latest,
  ): ScheduledTransitionsTokenReplacements {
    return new ScheduledTransitionsTokenReplacements(
      $scheduledTransition,
      $newRevision,
      $latest,
      $this->moderationInformation,
    );
  }

}
