<?php

declare(strict_types=1);

namespace DrevOps\VortexTooling\Tests\Unit;

use AlexSkrypnyk\PhpunitHelpers\Traits\EnvTrait;
use DrevOps\VortexTooling\Tests\Traits\MockTrait;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase as UpstreamUnitTestCase;

/**
 * Abstract base class for unit tests with helper methods.
 */
abstract class UnitTestCase extends UpstreamUnitTestCase {

  use MockTrait;
  use EnvTrait;

  protected function tearDown(): void {
    self::envReset();

    $this->mockTearDown();

    parent::tearDown();
  }

  protected function runScript(string $script, string $dir = 'src'): string {
    ob_start();

    // Change to src directory so __DIR__ works correctly in the script.
    $original_dir = getcwd();
    if ($original_dir === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Failed to get current working directory.');
      // @codeCoverageIgnoreEnd
    }

    $root = __DIR__ . '/../../src';
    if (!file_exists($root)) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Root directory not found: ' . $root);
      // @codeCoverageIgnoreEnd
    }

    chdir($root);
    try {
      require __DIR__ . '/../../' . $dir . '/' . $script;
    }
    finally {
      $output = ob_get_clean() ?: '';
      chdir($original_dir);
    }

    return $output;
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
