<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests jobs service.
 *
 * @group scheduled_transitions
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsJobs
 */
final class ScheduledTransitionsJobsTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use UserCreationTrait;

  protected static $modules = [
    'scheduled_transitions',
    'dynamic_entity_reference',
    'content_moderation',
    'workflows',
    'field',
    'user',
    'system',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('scheduled_transition');

    \Drupal::configFactory()->getEditable('scheduled_transitions.settings')->set('retain_processed', [
      'duration' => -1,
    ])->save(TRUE);
  }

  /**
   * Tests expired Scheduled Transitions.
   *
   * @covers ::cleanupExpired
   */
  public function testCleanupExpired(): void {
    /** @var \Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface $jobService */
    $jobService = \Drupal::service('scheduled_transitions.jobs');

    static::assertCount(0, ScheduledTransition::loadMultiple());

    $scheduledTransition = ScheduledTransition::create([
      'author' => 10,
      'is_processed' => TRUE,
      'processed_date' => (new \DateTimeImmutable('-1 year'))->getTimestamp(),
    ]);
    $scheduledTransition->save();

    static::assertCount(1, ScheduledTransition::loadMultiple());
    $jobService->cleanupExpired();
    static::assertCount(1, ScheduledTransition::loadMultiple());

    $scheduledTransition = ScheduledTransition::create([
      'author' => 10,
      'is_processed' => TRUE,
      'processed_date' => (new \DateTimeImmutable('+1 year'))->getTimestamp(),
    ]);
    $scheduledTransition->save();
    $id2 = $scheduledTransition->id();

    $scheduledTransition = ScheduledTransition::create([
      'author' => 10,
      'is_processed' => FALSE,
      'processed_date' => (new \DateTimeImmutable('-1 year'))->getTimestamp(),
    ]);
    $scheduledTransition->save();
    $id3 = $scheduledTransition->id();

    $scheduledTransition = ScheduledTransition::create([
      'author' => 10,
      'is_processed' => FALSE,
      'processed_date' => (new \DateTimeImmutable('+1 year'))->getTimestamp(),
    ]);
    $scheduledTransition->save();
    $id4 = $scheduledTransition->id();

    \Drupal::configFactory()->getEditable('scheduled_transitions.settings')->set('retain_processed', [
      'duration' => -3600,
    ])->save(TRUE);
    $jobService->cleanupExpired();
    $all = \array_keys(ScheduledTransition::loadMultiple());
    static::assertEquals([
      // Transition 1 was deleted.
      $id2,
      $id3,
      $id4,
    ], $all);
  }

}
