<?php

declare(strict_types=1);

namespace DrevOps\VortexTooling\Tests\Unit;

use AlexSkrypnyk\PhpunitHelpers\Traits\EnvTrait;
use phpmock\phpunit\PHPMock;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase as UpstreamUnitTestCase;

/**
 * Abstract base class for unit tests with helper methods.
 */
abstract class UnitTestCase extends UpstreamUnitTestCase {

  use PHPMock;
  use EnvTrait;

  protected function tearDown(): void {
    self::envReset();

    parent::tearDown();
  }

  protected function runScript(string $script, string $dir = 'src'): string {
    ob_start();
    // Change to src directory so __DIR__ works correctly in the script.
    $original_dir = getcwd();
    chdir(__DIR__ . '/../../src');
    require __DIR__ . '/../../' . $dir . '/' . $script;
    chdir($original_dir);
    return ob_get_clean();
  }

  /**
   * Mock curl functions to return predefined responses.
   *
   * @param array<int, array{status: int, body: string, error?: string|null}> $responses
   *   Array of responses to return for each curl call.
   *   Each response should have:
   *   - status: HTTP status code
   *   - body: Response body
   *   - error: Optional error message (defaults to null for success).
   * @param string $namespace
   *   Namespace to mock the functions in (defaults to DrevOps\VortexTooling).
   */
  protected function mockCurlMultiple(array $responses, string $namespace = 'DrevOps\\VortexTooling'): void {
    $curl_handle = 'mock_curl_handle';
    $exec_index = 0;
    $errno_index = 0;
    $error_index = 0;
    $getinfo_index = 0;

    // Mock curl_init - returns a handle.
    $curl_init = $this->getFunctionMock($namespace, 'curl_init');
    $curl_init->expects($this->exactly(count($responses)))->willReturn($curl_handle);

    // Mock curl_setopt_array - always returns true.
    $curl_setopt_array = $this->getFunctionMock($namespace, 'curl_setopt_array');
    $curl_setopt_array->expects($this->exactly(count($responses)))->willReturn(TRUE);

    // Mock curl_exec - returns response body for each call.
    $curl_exec = $this->getFunctionMock($namespace, 'curl_exec');
    $curl_exec->expects($this->exactly(count($responses)))
      ->willReturnCallback(function () use ($responses, &$exec_index) {
        $response = $responses[$exec_index++];
        return $response['body'];
      });

    // Mock curl_errno - returns 0 for success or 1 for error.
    $curl_errno = $this->getFunctionMock($namespace, 'curl_errno');
    $curl_errno->expects($this->exactly(count($responses)))
      ->willReturnCallback(function () use ($responses, &$errno_index): int {
        $response = $responses[$errno_index++];
        return isset($response['error']) && $response['error'] !== NULL ? 1 : 0;
      });

    // Mock curl_error - returns error message if present.
    $curl_error = $this->getFunctionMock($namespace, 'curl_error');
    $curl_error->expects($this->atLeast(0))
      ->willReturnCallback(function () use ($responses, &$error_index) {
        if ($error_index >= count($responses)) {
          return '';
        }
        $response = $responses[$error_index++];
        return $response['error'] ?? '';
      });

    // Mock curl_getinfo - returns HTTP status code.
    $curl_getinfo = $this->getFunctionMock($namespace, 'curl_getinfo');
    $curl_getinfo->expects($this->exactly(count($responses)))
      ->willReturnCallback(function () use ($responses, &$getinfo_index): array {
        $response = $responses[$getinfo_index++];
        return ['http_code' => $response['status']];
      });

    // Mock curl_close - does nothing.
    $curl_close = $this->getFunctionMock($namespace, 'curl_close');
    $curl_close->expects($this->exactly(count($responses)))->willReturn(NULL);
  }

  protected function mockCurl(array $response, string $namespace = 'DrevOps\\VortexTooling'): void {
    $this->mockCurlMultiple([$response], $namespace);
  }

}
