<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

interface ScheduledTransitionsTokenReplacementFactoryInterface {

  public function create(
    ScheduledTransitionInterface $scheduledTransition,
    RevisionableInterface $newRevision,
    RevisionableInterface $latest,
  ): ScheduledTransitionsTokenReplacements;

}
