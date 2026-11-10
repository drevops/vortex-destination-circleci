<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Interface for Scheduled Transitions configuration.
 */
interface ScheduledTransitionsConfigInterface {

  /**
   * Determine whether to create queue items in hook_cron invocations.
   *
   * @return bool
   *   Whether to create queue items in hook_cron invocations.
   */
  public function isCreatingQueueItemsInHookCron(): bool;

  /**
   * Determine if retaining a scheduled transition entity after processing.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition entity.
   *
   * @return bool
   *   Whether retaining a scheduled transition entity after processing.
   */
  public function isRetainingAfterProcessing(ScheduledTransitionInterface $scheduledTransition): bool;

  /**
   * Determine if retention duration is forever.
   *
   * @return bool
   *   Whether retention duration is forever.
   */
  public function isRetentionDurationForever(): bool;

  /**
   * Get retention duration.
   *
   * @return int<-1, max>
   *   The retention duration, where -1 signifies forever.
   */
  public function getRetentionDuration(): int;

  /**
   * Get the link template to link to from a processed scheduled transition.
   *
   * @return string|null
   *   A link template, or NULL to not link.
   */
  public function getProcessedLinkTemplate(RevisionableInterface $entity): ?string;

  /**
   * Log message used when shifting a former unpublished revision back on top.
   *
   * @param \Drupal\Core\Entity\RevisionLogInterface $revision
   *   The revision.
   *
   * @return string
   *   The message.
   */
  public function getMessageTransitionCopyLatestDraft(RevisionLogInterface $revision): string;

}
