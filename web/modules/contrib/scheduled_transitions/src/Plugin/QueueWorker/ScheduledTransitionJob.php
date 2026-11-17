<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\scheduled_transitions\Exception\ScheduledTransitionMissingEntity;
use Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs a scheduled transition.
 *
 * @QueueWorker(
 *   id = \Drupal\scheduled_transitions\Plugin\QueueWorker\ScheduledTransitionJob::PLUGIN_ID,
 *   title = @Translation("Scheduled transition job"),
 *   cron = {"time" = 900}
 * )
 */
class ScheduledTransitionJob extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public const PLUGIN_ID = 'scheduled_transition_job';

  /**
   * The key in data with the ID of a scheduled transition entity to process.
   */
  const SCHEDULED_TRANSITION_ID = 'scheduled_transition_id';

  final public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected EntityStorageInterface $scheduledTransitionStorage,
    protected ScheduledTransitionsRunnerInterface $scheduledTransitionsRunner,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(EntityTypeManagerInterface::class)->getStorage('scheduled_transition'),
      $container->get(ScheduledTransitionsRunnerInterface::class),
    );
  }

  public function processItem($data): void {
    /** @var array{scheduled_transition_id: string|int} $data */
    $id = $data[static::SCHEDULED_TRANSITION_ID];
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface|null $transition */
    $transition = $this->scheduledTransitionStorage->load($id);
    if ($transition === NULL) {
      return;
    }

    try {
      $this->scheduledTransitionsRunner->runTransition($transition);
    }
    catch (ScheduledTransitionMissingEntity) {
      $transition->delete();
    }
  }

  /**
   * Creates data for a queue item, suitable for usage with ::processItem.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition.
   *
   * @return mixed
   *   Queue item data.
   *
   * @internal There is no backwards compatibility promise, the return value
   *   may change at any time.
   */
  public static function createFrom(ScheduledTransitionInterface $scheduledTransition): mixed {
    return [
      ScheduledTransitionJob::SCHEDULED_TRANSITION_ID => $scheduledTransition->id(),
    ];
  }

}
