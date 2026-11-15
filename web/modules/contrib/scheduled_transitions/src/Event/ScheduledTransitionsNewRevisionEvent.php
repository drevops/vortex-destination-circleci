<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Event;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Used to determine the new revision for a transition.
 *
 * @see \Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents
 */
class ScheduledTransitionsNewRevisionEvent extends Event {

  /**
   * The new revision to transition.
   */
  protected ?RevisionableInterface $newRevision;

  public function __construct(
    protected ScheduledTransitionInterface $scheduledTransition,
  ) {
  }

  /**
   * Gets the scheduled transition entity.
   */
  public function getScheduledTransition(): ScheduledTransitionInterface {
    return $this->scheduledTransition;
  }

  /**
   * Get the new revision.
   */
  public function getNewRevision(): ?RevisionableInterface {
    return $this->newRevision;
  }

  /**
   * Sets the new revision.
   */
  public function setNewRevision(RevisionableInterface $newRevision): void {
    $this->newRevision = $newRevision;
  }

}
