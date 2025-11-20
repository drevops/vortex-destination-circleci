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

  /**
   * Mock curl functions to return predefined responses.
   *
   * @param array<int, array{status: int, body: string|bool, error?: string|int|null}> $responses
   *   Array of responses to return for each curl call.
   *   Each response should have:
   *   - status: HTTP status code
   *   - body: Response body
   *   - error: Optional error message (defaults to null for success).
   * @param string $namespace
   *   Namespace to mock the functions in (defaults to DrevOps\VortexTooling).
   *
   * @throws \RuntimeException
   *   When more curl requests are made than mocked responses available.
   */
  protected function mockCurlMultiple(array $responses, string $namespace = 'DrevOps\\VortexTooling'): void {
    $curl_handle = 'mock_curl_handle';
    $exec_index = 0;
    $errno_index = 0;
    $error_index = 0;
    $getinfo_index = 0;
    $total_responses = count($responses);

    // Mock curl_init - returns a handle.
    $curl_init = $this->getFunctionMock($namespace, 'curl_init');
    $curl_init->expects($this->any())
      ->willReturnCallback(function () use (&$exec_index, $total_responses, $curl_handle): string {
        if ($exec_index >= $total_responses) {
          throw new \RuntimeException(sprintf('curl_init() called more times than mocked responses. Expected %d request(s), but attempting request #%d.', $total_responses, $exec_index + 1));
        }
        return $curl_handle;
      });

    // Mock curl_setopt_array - always returns true.
    $curl_setopt_array = $this->getFunctionMock($namespace, 'curl_setopt_array');
    $curl_setopt_array->expects($this->any())->willReturn(TRUE);

    // Mock curl_exec - returns response body for each call.
    $curl_exec = $this->getFunctionMock($namespace, 'curl_exec');
    $curl_exec->expects($this->any())
      ->willReturnCallback(function () use ($responses, &$exec_index, $total_responses) {
        if ($exec_index >= $total_responses) {
          throw new \RuntimeException(sprintf('curl_exec() called more times than mocked responses. Expected %d request(s), but attempting request #%d.', $total_responses, $exec_index + 1));
        }
        $response = $responses[$exec_index++];
        return $response['body'];
      });

    // Mock curl_errno - returns 0 for success or 1 for error.
    $curl_errno = $this->getFunctionMock($namespace, 'curl_errno');
    $curl_errno->expects($this->any())
      ->willReturnCallback(function () use ($responses, &$errno_index, $total_responses): int {
        if ($errno_index >= $total_responses) {
          throw new \RuntimeException(sprintf('curl_errno() called more times than mocked responses. Expected %d request(s), but attempting request #%d.', $total_responses, $errno_index + 1));
        }
        $response = $responses[$errno_index++];
        return isset($response['error']) ? 1 : 0;
      });

    // Mock curl_error - returns error message if present.
    $curl_error = $this->getFunctionMock($namespace, 'curl_error');
    $curl_error->expects($this->any())
      ->willReturnCallback(function () use ($responses, &$error_index, $total_responses) {
        if ($error_index >= $total_responses) {
          return '';
        }
        $response = $responses[$error_index++];
        return $response['error'] ?? '';
      });

    // Mock curl_getinfo - returns HTTP status code.
    $curl_getinfo = $this->getFunctionMock($namespace, 'curl_getinfo');
    $curl_getinfo->expects($this->any())
      ->willReturnCallback(function () use ($responses, &$getinfo_index, $total_responses): array {
        if ($getinfo_index >= $total_responses) {
          throw new \RuntimeException(sprintf('curl_getinfo() called more times than mocked responses. Expected %d request(s), but attempting request #%d.', $total_responses, $getinfo_index + 1));
        }
        $response = $responses[$getinfo_index++];
        return ['http_code' => $response['status']];
      });

    // Mock curl_close - does nothing.
    $curl_close = $this->getFunctionMock($namespace, 'curl_close');
    $curl_close->expects($this->any())->willReturn(NULL);
  }

  /**
   * Mock curl single call to return predefined response.
   *
   * @param array{status: int, body: string|bool, error?: string|int|null} $response
   *   A single responses to return for one curl call.
   *   Each response should have:
   *   - status: HTTP status code
   *   - body: Response body
   *   - error: Optional error message (defaults to null for success).
   * @param string $namespace
   *   Namespace to mock the functions in (defaults to DrevOps\VortexTooling).
   *
   * @throws \RuntimeException
   *   When more curl requests are made than mocked responses available.
   */
  protected function mockCurl(array $response, string $namespace = 'DrevOps\\VortexTooling'): void {
    $this->mockCurlMultiple([$response], $namespace);
  }

}
