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

  /**
   * Stores curl mock objects.
   *
   * @var array<string, \PHPUnit\Framework\MockObject\MockObject>
   */
  protected array $mockCurl = [];

  /**
   * Stores curl responses for the mock.
   *
   * @var array<int, array{url: string, method?: string, response: array{ok: bool, status: int, body: string|false, error: string|null, info: array<string, mixed>}}>
   */
  protected array $mockCurlResponses = [];

  /**
   * Current index for curl responses.
   */
  protected int $mockCurlIndex = 0;

  /**
   * Flag to track if curl mocks were already checked.
   */
  protected bool $mockCurlChecked = FALSE;

  protected function mockTearDown(): void {
    // Verify all mocked passthru responses were consumed.
    $this->mockPassthruAssertAllMocksConsumed();

    // Reset passthru mock.
    $this->mockPassthru = NULL;
    $this->mockPassthruResponses = [];
    $this->mockPassthruIndex = 0;
    $this->mockPassthruChecked = FALSE;

    // Verify all mocked curl responses were consumed.
    $this->mockCurlAssertAllMocksConsumed();

    // Reset curl mock.
    $this->mockCurl = [];
    $this->mockCurlResponses = [];
    $this->mockCurlIndex = 0;
    $this->mockCurlChecked = FALSE;
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
   * @param array<int, array{url: string, method?: string, response: array{ok?: bool, status: int, body?: string|false, error?: string|null, info?: array<string, mixed>}}> $responses
   *   Array of responses to return for each curl call.
   *   Each response should have:
   *   - url: Expected URL (required)
   *   - method: Expected HTTP method (optional)
   *   - response: Response data with status, body, error, info.
   * @param string $namespace
   *   Namespace to mock the functions in (defaults to DrevOps\VortexTooling).
   *
   * @throws \RuntimeException
   *   When more curl requests are made than mocked responses available.
   */
  protected function mockCurlMultiple(array $responses, string $namespace = 'DrevOps\\VortexTooling'): void {
    // Add responses to the class property.
    $this->mockCurlResponses = array_merge($this->mockCurlResponses, $responses);

    // If mocks already exist, just add to responses and return.
    if (!empty($this->mockCurl)) {
      return;
    }

    // Track state across all curl function calls.
    $current_url = NULL;
    $current_method = NULL;

    // Mock curl_init - stores URL and returns handle.
    $this->mockCurl['curl_init'] = $this->getFunctionMock($namespace, 'curl_init');
    $this->mockCurl['curl_init']->expects($this->any())
      ->willReturnCallback(function ($url = NULL) use (&$current_url): string {
        $total_responses = count($this->mockCurlResponses);

        if ($this->mockCurlIndex >= $total_responses) {
          throw new \RuntimeException(sprintf('curl_init() called more times than mocked responses. Expected %d request(s), but attempting request #%d.', $total_responses, $this->mockCurlIndex + 1));
        }

        $current_url = $url;
        return 'mock_curl_handle';
      });

    // Mock curl_setopt_array - extracts method from options.
    $this->mockCurl['curl_setopt_array'] = $this->getFunctionMock($namespace, 'curl_setopt_array');
    $this->mockCurl['curl_setopt_array']->expects($this->any())
      ->willReturnCallback(function ($ch, $options) use (&$current_method): bool {
        if (isset($options[CURLOPT_CUSTOMREQUEST])) {
          $current_method = $options[CURLOPT_CUSTOMREQUEST];
        }
        return TRUE;
      });

    // Mock curl_exec - validates and returns response body.
    $this->mockCurl['curl_exec'] = $this->getFunctionMock($namespace, 'curl_exec');
    $this->mockCurl['curl_exec']->expects($this->any())
      ->willReturnCallback(function () use (&$current_url, &$current_method): string|false {
        $total_responses = count($this->mockCurlResponses);

        if ($this->mockCurlIndex >= $total_responses) {
          throw new \RuntimeException(sprintf('curl_exec() called more times than mocked responses. Expected %d request(s), but attempting request #%d.', $total_responses, $this->mockCurlIndex + 1));
        }

        $mock = $this->mockCurlResponses[$this->mockCurlIndex];

        // Validate response structure.
        if (!isset($mock['url'])) {
          throw new \InvalidArgumentException('Mocked curl response must include "url" key to specify expected URL.');
        }

        // Validate URL matches.
        if ($mock['url'] !== $current_url) {
          throw new \RuntimeException(sprintf('curl request made to unexpected URL. Expected "%s", got "%s".', $mock['url'], $current_url));
        }

        // Validate method if specified.
        if (isset($mock['method']) && $mock['method'] !== $current_method) {
          throw new \RuntimeException(sprintf('curl request made with unexpected method. Expected "%s", got "%s".', $mock['method'], $current_method ?? 'GET'));
        }

        // Apply defaults to response.
        $response = $mock['response'] + [
          'ok' => TRUE,
          'status' => 200,
          'body' => '',
          'error' => NULL,
          'info' => [],
        ];

        return $response['body'];
      });

    // Mock curl_errno - returns 0 for success or non-zero for error.
    $this->mockCurl['curl_errno'] = $this->getFunctionMock($namespace, 'curl_errno');
    $this->mockCurl['curl_errno']->expects($this->any())
      ->willReturnCallback(function (): int {
        $mock = $this->mockCurlResponses[$this->mockCurlIndex];
        $response = $mock['response'] + ['error' => NULL];
        return $response['error'] !== NULL ? 1 : 0;
      });

    // Mock curl_error - returns error message if present.
    $this->mockCurl['curl_error'] = $this->getFunctionMock($namespace, 'curl_error');
    $this->mockCurl['curl_error']->expects($this->any())
      ->willReturnCallback(function (): string {
        $mock = $this->mockCurlResponses[$this->mockCurlIndex];
        $response = $mock['response'] + ['error' => NULL];
        return $response['error'] ?? '';
      });

    // Mock curl_getinfo - returns info array with http_code.
    $this->mockCurl['curl_getinfo'] = $this->getFunctionMock($namespace, 'curl_getinfo');
    $this->mockCurl['curl_getinfo']->expects($this->any())
      ->willReturnCallback(function (): array {
        $mock = $this->mockCurlResponses[$this->mockCurlIndex];
        $response = $mock['response'] + ['status' => 200, 'info' => []];
        $info = $response['info'] + ['http_code' => $response['status']];
        return $info;
      });

    // Mock curl_close - increments index when curl handle is closed.
    $this->mockCurl['curl_close'] = $this->getFunctionMock($namespace, 'curl_close');
    $this->mockCurl['curl_close']->expects($this->any())
      ->willReturnCallback(function () use (&$current_url, &$current_method): void {
        $this->mockCurlIndex++;
        $current_url = NULL;
        $current_method = NULL;
      });
  }

  /**
   * Mock single curl call.
   *
   * @param array{url: string, method?: string, response: array{ok?: bool, status: int, body?: string|false, error?: string|null, info?: array<string, mixed>}} $response
   *   Response with url, optional method, and response data.
   * @param string $namespace
   *   Namespace to mock the functions in.
   */
  protected function mockCurl(array $response, string $namespace = 'DrevOps\\VortexTooling'): void {
    $this->mockCurlMultiple([$response], $namespace);
  }

  /**
   * Verify all mocked curl responses were consumed.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   When not all mocked responses were consumed.
   */
  protected function mockCurlAssertAllMocksConsumed(): void {
    if (!empty($this->mockCurlResponses) && !$this->mockCurlChecked) {
      $this->mockCurlChecked = TRUE;

      $total_responses = count($this->mockCurlResponses);
      $consumed_responses = $this->mockCurlIndex;

      if ($consumed_responses < $total_responses) {
        $this->fail(sprintf('Not all mocked curl responses were consumed. Expected %d request(s), but only %d request(s) were made.', $total_responses, $consumed_responses));
      }
    }
  }

}
