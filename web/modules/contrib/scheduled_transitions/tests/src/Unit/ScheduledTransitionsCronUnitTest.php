<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Unit;

use Drupal\scheduled_transitions\ScheduledTransitionsConfigInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsHooks;
use Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface;

/**
 * Tests cron hooks.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsHooks
 * @group scheduled_transitions
 */
class ScheduledTransitionsCronUnitTest extends ScheduledTransitionUnitTestBase {

  /**
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsConfigInterface&\Mockery\MockInterface
   */
  private ScheduledTransitionsConfigInterface $testConfig;

  /**
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface&\Mockery\MockInterface
   */
  private ScheduledTransitionsJobsInterface $testJobs;

  protected function setUp(): void {
    parent::setUp();
    $this->testConfig = \Mockery::mock(ScheduledTransitionsConfigInterface::class);
    $this->testConfig
      ->expects('isRetentionDurationForever')
      ->once()
      ->andReturn(TRUE);
    $this->testJobs = \Mockery::mock(ScheduledTransitionsJobsInterface::class);
  }

  /**
   * Tests creating queue items during cron.
   *
   * @covers ::cron
   */
  public function testCronOn(): void {
    $this->testConfig
      ->expects('isCreatingQueueItemsInHookCron')
      ->once()
      ->andReturn(TRUE);
    $this->testJobs
      ->expects('jobCreator')
      ->once()
      ->andReturn(TRUE);

    $hooksService = new ScheduledTransitionsHooks($this->testConfig, $this->testJobs);
    $hooksService->cron();
  }

  /**
   * Tests not creating queue items during cron.
   *
   * @covers ::cron
   */
  public function testCronOff(): void {
    $this->testConfig
      ->expects('isCreatingQueueItemsInHookCron')
      ->once()
      ->andReturn(FALSE);
    $this->testJobs
      ->expects('jobCreator')
      ->never()
      ->andReturn(TRUE);

    $hooksService = new ScheduledTransitionsHooks($this->testConfig, $this->testJobs);
    $hooksService->cron();
  }

}
