<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Unit;

use Drupal\Tests\UnitTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Adapter\Phpunit\MockeryTestCaseSetUp;

/**
 * Base class for unit testing.
 */
abstract class ScheduledTransitionUnitTestBase extends UnitTestCase {

  use MockeryPHPUnitIntegration;
  use MockeryTestCaseSetUp;

  protected function mockeryTestSetUp(): void {
  }

  protected function mockeryTestTearDown(): void {
  }

}
