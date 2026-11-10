<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

/**
 * Hooks for Scheduled Transitions module.
 */
final class ScheduledTransitionsHooks {

  final public function __construct(
    private ScheduledTransitionsConfigInterface $scheduledTransitionsConfig,
    private ScheduledTransitionsJobsInterface $scheduledTransitionsJobs,
  ) {
  }

  /**
   * Implements hook_cron().
   *
   * @see \scheduled_transitions_cron()
   */
  public function cron(): void {
    if ($this->scheduledTransitionsConfig->isCreatingQueueItemsInHookCron()) {
      $this->scheduledTransitionsJobs->jobCreator();
    }

    if ($this->scheduledTransitionsConfig->isRetentionDurationForever() === FALSE) {
      $this->scheduledTransitionsJobs->cleanupExpired();
    }
  }

}
