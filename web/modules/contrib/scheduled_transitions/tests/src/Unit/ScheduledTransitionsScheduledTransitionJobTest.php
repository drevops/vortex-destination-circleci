<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Unit;

use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\scheduled_transitions\Plugin\QueueWorker\ScheduledTransitionJob;

/**
 * Tests queue class.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\Plugin\QueueWorker\ScheduledTransitionJob
 * @group scheduled_transitions
 */
final class ScheduledTransitionsScheduledTransitionJobTest extends ScheduledTransitionUnitTestBase {

  /**
   * Tests queue item data utility.
   *
   * @covers ::createFrom
   */
  public function testCreateFrom(): void {
    $scheduledTransition = \Mockery::mock(ScheduledTransitionInterface::class);
    $scheduledTransition
      ->expects('id')
      ->andReturn('1337');
    static::assertEquals(
      [
        'scheduled_transition_id' => '1337',
      ],
      ScheduledTransitionJob::createFrom($scheduledTransition),
    );
  }

}
