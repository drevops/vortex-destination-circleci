<?php

declare(strict_types=1);

namespace DrevOps\VortexTooling\Tests\Traits;

use DrevOps\VortexTooling\Tests\Exceptions\QuitErrorException;
use DrevOps\VortexTooling\Tests\Exceptions\QuitSuccessException;
use phpmock\phpunit\PHPMock;

trait MockTrait {

  use PHPMock;

  /**
   * Stores the passthru mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|null
   */
  protected $mockPassthru;

  /**
   * Stores passthru responses for the mock.
   *
   * @var array<int, array{cmd:string, output?: string, result_code?: int, return?: NULL|FALSE}>
   */
  protected array $mockPassthruResponses = [];

  /**
   * Current index for passthru responses.
   */
  protected int $mockPassthruIndex = 0;

  /**
   * Flag to track if passthru mocks were already checked.
   */
  protected bool $mockPassthruChecked = FALSE;

  protected function mockTearDown(): void {
    // Verify all mocked passthru responses were consumed.
    $this->mockPassthruAssertAllMocksConsumed();

    // Reset passthru mock.
    $this->mockPassthru = NULL;
    $this->mockPassthruResponses = [];
    $this->mockPassthruIndex = 0;
    $this->mockPassthruChecked = FALSE;
  }

  /**
   * Mock passthru function to return predefined output and exit codes.
   *
   * @param array<int, array{cmd:string, output?: string, result_code?: int, return?: NULL|FALSE}> $responses
   *   Array of responses to return for each passthru call.
   *   Each response should have:
   *   - output: The output to echo
   *   - exit_code: The exit code to set (0 for success).
   * @param string $namespace
   *   Namespace to mock the functions in (defaults to DrevOps\VortexTooling).
   *
   * @throws \RuntimeException
   *   When more passthru calls are made than mocked responses available.
   */
  protected function mockPassthruMultiple(array $responses, string $namespace = 'DrevOps\\VortexTooling'): void {
    // Add responses to the class property.
    $this->mockPassthruResponses = array_merge($this->mockPassthruResponses, $responses);

    // If mock already exists, just add to responses and return.
    if ($this->mockPassthru !== NULL) {
      return;
    }

    // Create and store the mock.
    $this->mockPassthru = $this->getFunctionMock($namespace, 'passthru');
    $this->mockPassthru
      ->expects($this->any())
      ->willReturnCallback(function ($command, &...$args): null|false {
        $total_responses = count($this->mockPassthruResponses);

        if ($this->mockPassthruIndex >= $total_responses) {
          throw new \RuntimeException(sprintf('passthru() called more times than mocked responses. Expected %d call(s), but attempting call #%d.', $total_responses, $this->mockPassthruIndex + 1));
        }

        $response = $this->mockPassthruResponses[$this->mockPassthruIndex++];

        $response += [
          'output' => '',
          'result_code' => 0,
          'return' => NULL,
        ];

        // Validate response structure.
        // @phpstan-ignore-next-line isset.offset
        if (!isset($response['cmd'])) {
          throw new \InvalidArgumentException('Mocked passthru response must include "cmd" key to specify expected command.');
        }

        // @phpstan-ignore-next-line booleanAnd.alwaysFalse
        if ($response['return'] !== FALSE && $response['return'] !== NULL) {
          throw new \InvalidArgumentException(sprintf('Mocked passthru response "return" key must be either NULL or FALSE, but got %s.', gettype($response['return'])));
        }

        // Expectation error.
        if ($response['cmd'] !== $command) {
          throw new \RuntimeException(sprintf('passthru() called with unexpected command. Expected "%s", got "%s".', $response['cmd'], $command));
        }

        echo $response['output'];

        // Set exit code only if it was passed by reference.
        // Using spread operator to distinguish between no argument and NULL.
        if (count($args) > 0) {
          $args[0] = $response['result_code'];
        }

        return $response['return'];
      });
  }

  /**
   * Mock single passthru call.
   *
   * @param array{cmd:string, output?: string, result_code?: int, return?: NULL|FALSE} $response
   *   Response with output and exit_code.
   * @param string $namespace
   *   Namespace to mock the functions in.
   */
  protected function mockPassthru(array $response, string $namespace = 'DrevOps\\VortexTooling'): void {
    $this->mockPassthruMultiple([$response], $namespace);
  }

  /**
   * Verify all mocked passthru responses were consumed.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   When not all mocked responses were consumed.
   */
  protected function mockPassthruAssertAllMocksConsumed(): void {
    if ($this->mockPassthru !== NULL && !$this->mockPassthruChecked) {
      $this->mockPassthruChecked = TRUE;

      $total_responses = count($this->mockPassthruResponses);
      $consumed_responses = $this->mockPassthruIndex;

      if ($consumed_responses < $total_responses) {
        $this->fail(sprintf('Not all mocked passthru responses were consumed. Expected %d call(s), but only %d call(s) were made.', $total_responses, $consumed_responses));
      }
    }
  }

  /**
   * Mock quit() function to throw QuitErrorException instead of terminating.
   *
   * This allows testing code that calls quit() without actually exiting.
   * The mock will throw an QuitErrorException with the exit code, which can be
   * caught and asserted in tests.
   *
   * @param int $code
   *   Exit code to expect (0 for success, non-zero for error).
   * @param string $namespace
   *   Namespace to mock the function in (defaults to DrevOps\VortexTooling).
   */
  protected function mockQuit(int $code = 0, string $namespace = 'DrevOps\\VortexTooling'): void {
    $quit = $this->getFunctionMock($namespace, 'quit');
    $quit
      ->expects($this->any())
      ->willReturnCallback(function (int $exit_code = 0) use ($code): void {
        // Expectation error.
        if ($code !== $exit_code) {
          throw new \RuntimeException(sprintf('quit() called with unexpected exit code. Expected %d, got %d.', $code, $exit_code));
        }
        // Non-zero exit code throws QuitErrorException to simulate exit.
        if ($code !== 0) {
          throw new QuitErrorException($code);
        }

        throw new QuitSuccessException($code);
      });
  }

}
