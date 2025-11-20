<?php

declare(strict_types=1);

namespace DrevOps\VortexTooling\Tests\Unit;

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/UnitTestCase.php';

/**
 * Self-tests for the testing infrastructure.
 *
 * These tests verify that our test system itself works correctly,
 * including mocking capabilities and script execution.
 */
class SelfTest extends UnitTestCase {

  /**
   * Test that mockCurl helper works with single request in fixture script.
   */
  public function testMockCurlSingleRequest(): void {
    // Mock curl to return a successful response.
    $this->mockCurl([
      'status' => 200,
      'body' => '<html><body>Test response</body></html>',
    ]);

    // Set up environment.
    self::envSet('TEST_CURL_URL', 'https://example.com');

    // Execute the test fixture script using runScript helper.
    $output = $this->runScript('test-curl-script.php', 'tests/Fixtures');

    // Verify output.
    $this->assertStringContainsString('[INFO] Starting test curl script.', $output);
    $this->assertStringContainsString('[INFO] Making request to: https://example.com', $output);
    $this->assertStringContainsString('[ OK ] Request successful.', $output);
    $this->assertStringContainsString('Status: 200', $output);
    $this->assertStringContainsString('Body length:', $output);
    $this->assertStringContainsString('[ OK ] Finished test curl script.', $output);
  }

  /**
   * Test that mockCurlMultiple helper works with multiple sequential requests.
   */
  public function testMockCurlMultipleRequests(): void {
    // Mock curl to return multiple responses.
    $this->mockCurlMultiple([
      [
        'status' => 200,
        'body' => 'First response',
      ],
      [
        'status' => 201,
        'body' => 'Second response',
      ],
    ]);

    // Make multiple curl requests.
    $response1 = curl_get('https://example.com/first');
    $response2 = curl_get('https://example.com/second');

    // Verify first response.
    $this->assertTrue($response1['ok']);
    $this->assertEquals(200, $response1['status']);
    $this->assertEquals('First response', $response1['body']);

    // Verify second response.
    $this->assertTrue($response2['ok']);
    $this->assertEquals(201, $response2['status']);
    $this->assertEquals('Second response', $response2['body']);
  }

  /**
   * Test that mockCurl helper handles error responses.
   */
  public function testMockCurlErrorResponse(): void {
    // Mock curl to return an error.
    $this->mockCurl([
      'status' => 0,
      'body' => FALSE,
      'error' => 'Connection timeout',
    ]);

    // Make request that should fail.
    $response = curl_get('https://example.com/error');

    // Verify error response.
    $this->assertFalse($response['ok']);
    $this->assertEquals(0, $response['status']);
    $this->assertFalse($response['body']);
    $this->assertEquals('Connection timeout', $response['error']);
  }

  /**
   * Test that mockCurlMultiple works with different HTTP status codes.
   */
  public function testMockCurlDifferentStatusCodes(): void {
    // Mock curl to return various status codes.
    $this->mockCurlMultiple([
      [
        'status' => 404,
        'body' => 'Not Found',
      ],
      [
        'status' => 500,
        'body' => 'Internal Server Error',
      ],
    ]);

    // Test 404 response.
    $response404 = curl_get('https://example.com/notfound');
    $this->assertFalse($response404['ok']);
    $this->assertEquals(404, $response404['status']);
    $this->assertEquals('Not Found', $response404['body']);

    // Test 500 response.
    $response500 = curl_get('https://example.com/error');
    $this->assertFalse($response500['ok']);
    $this->assertEquals(500, $response500['status']);
    $this->assertEquals('Internal Server Error', $response500['body']);
  }

}
