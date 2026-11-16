<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Commands;

use Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush command for Scheduled Transitions.
 */
class ScheduledTransitionsCommands extends DrushCommands {

  public function __construct(
    protected ScheduledTransitionsJobsInterface $jobs,
  ) {
    parent::__construct();
  }

  /**
   * Fills queue with crawler jobs.
   *
   * @command scheduled-transitions:queue-jobs
   * @aliases sctr-jobs
   */
  public function crawlJobCreator(): void {
    $this->jobs->jobCreator();
    $this->logger()->success(\dt('Scheduled transitions queued.'));
  }

}
