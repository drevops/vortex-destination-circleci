<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\scheduled_transitions\Plugin\QueueWorker\ScheduledTransitionJob;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Job runner for Scheduled Transitions.
 */
class ScheduledTransitionsJobs implements ScheduledTransitionsJobsInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * Duration a scheduled transition should be locked from adding to queue.
   */
  protected const LOCK_DURATION = 1800;

  protected QueueInterface $queue;

  final public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    QueueFactory $queueFactory,
    private ScheduledTransitionsConfigInterface $scheduledTransitionsConfig,
  ) {
    $this->queue = $queueFactory->get(ScheduledTransitionJob::PLUGIN_ID);
  }

  public function jobCreator(): void {
    $transitionStorage = $this->entityTypeManager
      ->getStorage('scheduled_transition');

    $now = $this->time->getRequestTime();
    $query = $transitionStorage->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('transition_on', $now, '<=');
    $query->condition('is_processed', '1', '<>');
    $or = $query->orConditionGroup()
      ->condition('locked_on', NULL, 'IS NULL')
      ->condition('locked_on', $now - static::LOCK_DURATION, '>=');
    $query->condition($or);
    $ids = $query->execute();

    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface[] $transitions */
    $transitions = $transitionStorage->loadMultiple($ids);
    foreach ($transitions as $transition) {
      $transition->setLockedOn($now)->save();
      $this->queue->createItem(ScheduledTransitionJob::createFrom($transition));
      $this->logger->info('Created scheduled transition job for #@id', [
        '@id' => $transition->id(),
      ]);
    }
  }

  public function cleanupExpired(): void {
    if ($this->scheduledTransitionsConfig->isRetentionDurationForever()) {
      return;
    }

    /** @var int<0, max> $lifetimeSeconds */
    $lifetimeSeconds = $this->scheduledTransitionsConfig->getRetentionDuration();

    $transitionStorage = $this->entityTypeManager
      ->getStorage('scheduled_transition');

    $now = $this->time->getRequestTime();
    $query = $transitionStorage->getQuery();
    $ids = $query
      ->accessCheck(FALSE)
      ->condition('is_processed', '0', '<>')
      ->condition('processed_date', operator: 'IS NOT NULL')
      ->condition('processed_date', $now - $lifetimeSeconds, '<')
      ->execute();

    $transitionStorage->delete($transitionStorage->loadMultiple($ids));
  }

}
