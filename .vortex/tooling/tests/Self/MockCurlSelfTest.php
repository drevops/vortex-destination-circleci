<?php

declare(strict_types=1);

namespace DrevOps\VortexTooling\Tests\Self;

use DrevOps\VortexTooling\Tests\Unit\CoversClass;
use DrevOps\VortexTooling\Tests\Unit\UnitTestCase;

/**
 * Self-tests for mocking of curl_ functions.
 *
 * We test mockCurl() to ensure it returns the correct responses
 * and validates URL/method expectations.
 */
#[CoversClass(UnitTestCase::class)]
class MockCurlSelfTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Load helpers.php to make curl_get/curl_post/curl_request available.
    require_once __DIR__ . '/../../src/helpers.php';
  }

  // =========================================================================
  // DIRECT TESTS
  // =========================================================================

  public function testMockCurlGetSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'response' => [
        'status' => 200,
        'body' => 'success response',
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_get('https://example.com/api');

    $this->assertTrue($result['ok']);
    $this->assertEquals(200, $result['status']);
    $this->assertEquals('success response', $result['body']);
    $this->assertNull($result['error']);
  }

  public function testMockCurlGetCustomSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'response' => [
        'ok' => TRUE,
        'status' => 201,
        'body' => 'custom body content',
        'error' => NULL,
        'info' => ['http_code' => 201, 'total_time' => 1.5],
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_get('https://example.com/api');

    $this->assertTrue($result['ok']);
    $this->assertEquals(201, $result['status']);
    $this->assertEquals('custom body content', $result['body']);
    $this->assertNull($result['error']);
    $this->assertEquals(201, $result['info']['http_code']);
    $this->assertEquals(1.5, $result['info']['total_time']);
  }

  public function testMockCurlGetDefaultsSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'response' => [
        'status' => 200,
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_get('https://example.com/api');

    $this->assertTrue($result['ok']);
    $this->assertEquals(200, $result['status']);
    $this->assertEquals('', $result['body']);
    $this->assertNull($result['error']);
  }

  public function testMockCurlGetFailure404(): void {
    $this->mockCurl([
      'url' => 'https://example.com/not-found',
      'response' => [
        'ok' => FALSE,
        'status' => 404,
        'body' => 'Not Found',
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_get('https://example.com/not-found');

    $this->assertFalse($result['ok']);
    $this->assertEquals(404, $result['status']);
    $this->assertEquals('Not Found', $result['body']);
  }

  public function testMockCurlGetFailure500(): void {
    $this->mockCurl([
      'url' => 'https://example.com/error',
      'response' => [
        'ok' => FALSE,
        'status' => 500,
        'body' => 'Internal Server Error',
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_get('https://example.com/error');

    $this->assertFalse($result['ok']);
    $this->assertEquals(500, $result['status']);
    $this->assertEquals('Internal Server Error', $result['body']);
  }

  public function testMockCurlGetNetworkError(): void {
    $this->mockCurl([
      'url' => 'https://example.com/timeout',
      'response' => [
        'ok' => FALSE,
        'status' => 0,
        'body' => FALSE,
        'error' => 'Could not resolve host',
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_get('https://example.com/timeout');

    $this->assertFalse($result['ok']);
    $this->assertEquals(0, $result['status']);
    $this->assertFalse($result['body']);
    $this->assertEquals('Could not resolve host', $result['error']);
  }

  public function testMockCurlPostSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'method' => 'POST',
      'response' => [
        'status' => 201,
        'body' => 'created',
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_post('https://example.com/api', '{"key":"value"}');

    $this->assertTrue($result['ok']);
    $this->assertEquals(201, $result['status']);
    $this->assertEquals('created', $result['body']);
  }

  public function testMockCurlPostJsonSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api/json',
      'method' => 'POST',
      'response' => [
        'status' => 200,
        'body' => '{"success":true}',
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_post(
      'https://example.com/api/json',
      json_encode(['data' => 'value']),
      ['Content-Type: application/json']
    );

    $this->assertTrue($result['ok']);
    $this->assertEquals(200, $result['status']);
    $this->assertEquals('{"success":true}', $result['body']);
  }

  public function testMockCurlRequestCustomMethodSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/resource',
      'method' => 'PUT',
      'response' => [
        'status' => 200,
        'body' => 'updated',
      ],
    ]);

    $result = \DrevOps\VortexTooling\curl_request('https://example.com/resource', ['method' => 'PUT']);

    $this->assertTrue($result['ok']);
    $this->assertEquals(200, $result['status']);
    $this->assertEquals('updated', $result['body']);
  }

  public function testMockCurlMultipleSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/first',
      'response' => [
        'status' => 200,
        'body' => 'first response',
      ],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/second',
      'method' => 'POST',
      'response' => [
        'status' => 201,
        'body' => 'second response',
      ],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/third',
      'method' => 'PUT',
      'response' => [
        'status' => 200,
        'body' => 'third response',
      ],
    ]);

    $result1 = \DrevOps\VortexTooling\curl_get('https://example.com/first');
    $this->assertEquals(200, $result1['status']);
    $this->assertEquals('first response', $result1['body']);

    $result2 = \DrevOps\VortexTooling\curl_post('https://example.com/second', 'data');
    $this->assertEquals(201, $result2['status']);
    $this->assertEquals('second response', $result2['body']);

    $result3 = \DrevOps\VortexTooling\curl_request('https://example.com/third', ['method' => 'PUT']);
    $this->assertEquals(200, $result3['status']);
    $this->assertEquals('third response', $result3['body']);
  }

  public function testMockCurlMultipleMoreCallsFailure(): void {
    $this->mockCurl([
      'url' => 'https://example.com/first',
      'response' => ['status' => 200, 'body' => 'first'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/second',
      'response' => ['status' => 200, 'body' => 'second'],
    ]);

    \DrevOps\VortexTooling\curl_get('https://example.com/first');
    \DrevOps\VortexTooling\curl_get('https://example.com/second');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('curl_init() called more times than mocked responses. Expected 2 request(s), but attempting request #3');

    \DrevOps\VortexTooling\curl_get('https://example.com/third');
  }

  public function testMockCurlMultipleLessCallsFailure(): void {
    $this->mockCurl([
      'url' => 'https://example.com/first',
      'response' => ['status' => 200, 'body' => 'first'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/second',
      'response' => ['status' => 200, 'body' => 'second'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/third',
      'response' => ['status' => 200, 'body' => 'third'],
    ]);

    \DrevOps\VortexTooling\curl_get('https://example.com/first');
    \DrevOps\VortexTooling\curl_get('https://example.com/second');

    $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
    $this->expectExceptionMessage('Not all mocked curl responses were consumed. Expected 3 request(s), but only 2 request(s) were made.');

    // Manually trigger the check that normally happens in tearDown().
    $this->mockCurlAssertAllMocksConsumed();
  }

  public function testMockCurlFailureArgumentExceptionUrl(): void {
    $this->mockCurl([
      'response' => ['status' => 200],
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Mocked curl response must include "url" key to specify expected URL.');

    \DrevOps\VortexTooling\curl_get('https://example.com/api');
  }

  public function testMockCurlFailureAssertUnexpectedUrl(): void {
    $this->mockCurl([
      'url' => 'https://example.com/expected',
      'response' => ['status' => 200],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('curl request made to unexpected URL. Expected "https://example.com/expected", got "https://example.com/actual".');

    \DrevOps\VortexTooling\curl_get('https://example.com/actual');
  }

  // =========================================================================
  // SCRIPT TESTS
  // =========================================================================

  public function testMockCurlScriptGetSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'response' => [
        'status' => 200,
        'body' => 'success response',
      ],
    ]);

    $output = $this->runScript('test-curl-get-success', 'tests/Fixtures');

    $this->assertStringContainsString('Script will call curl_get', $output);
    $this->assertStringContainsString('Response status: 200', $output);
    $this->assertStringContainsString('Response ok: true', $output);
    $this->assertStringContainsString('Response body: success response', $output);
  }

  public function testMockCurlScriptGetFailure(): void {
    $this->mockCurl([
      'url' => 'https://example.com/not-found',
      'response' => [
        'ok' => FALSE,
        'status' => 404,
        'body' => 'Not Found',
      ],
    ]);

    $output = $this->runScript('test-curl-get-failure', 'tests/Fixtures');

    $this->assertStringContainsString('Script will call curl_get expecting failure', $output);
    $this->assertStringContainsString('Response status: 404', $output);
    $this->assertStringContainsString('Response ok: false', $output);
    $this->assertStringContainsString('Request failed as expected', $output);
  }

  public function testMockCurlScriptGetCustomSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'response' => [
        'status' => 201,
        'body' => 'custom response',
      ],
    ]);

    $output = $this->runScript('test-curl-get-success', 'tests/Fixtures');

    $this->assertStringContainsString('Response status: 201', $output);
    $this->assertStringContainsString('Response body: custom response', $output);
  }

  public function testMockCurlScriptGetDefaultsSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'response' => [
        'status' => 200,
      ],
    ]);

    $output = $this->runScript('test-curl-get-success', 'tests/Fixtures');

    $this->assertStringContainsString('Response status: 200', $output);
    $this->assertStringContainsString('Response ok: true', $output);
  }

  public function testMockCurlScriptGetNetworkError(): void {
    $this->mockCurl([
      'url' => 'https://example.com/not-found',
      'response' => [
        'ok' => FALSE,
        'status' => 0,
        'body' => FALSE,
        'error' => 'Could not resolve host',
      ],
    ]);

    $output = $this->runScript('test-curl-get-failure', 'tests/Fixtures');

    $this->assertStringContainsString('Response status: 0', $output);
    $this->assertStringContainsString('Response ok: false', $output);
  }

  public function testMockCurlScriptPostSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/api',
      'method' => 'POST',
      'response' => [
        'status' => 201,
        'body' => 'created',
      ],
    ]);

    $output = $this->runScript('test-curl-post-success', 'tests/Fixtures');

    $this->assertStringContainsString('Script will call curl_post', $output);
    $this->assertStringContainsString('Response status: 201', $output);
    $this->assertStringContainsString('Response ok: true', $output);
    $this->assertStringContainsString('POST succeeded', $output);
  }

  public function testMockCurlScriptMultipleSuccess(): void {
    $this->mockCurl([
      'url' => 'https://example.com/first',
      'response' => ['status' => 200, 'body' => 'first'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/second',
      'method' => 'POST',
      'response' => ['status' => 201, 'body' => 'second'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/third',
      'method' => 'PUT',
      'response' => ['status' => 200, 'body' => 'third'],
    ]);

    $output = $this->runScript('test-curl-multiple', 'tests/Fixtures');

    $this->assertStringContainsString('Script will call curl multiple times', $output);
    $this->assertStringContainsString('First call status: 200', $output);
    $this->assertStringContainsString('Second call status: 201', $output);
    $this->assertStringContainsString('Third call status: 200', $output);
    $this->assertStringContainsString('Script completed', $output);
  }

  public function testMockCurlScriptMultipleMoreCallsFailure(): void {
    $this->mockCurl([
      'url' => 'https://example.com/first',
      'response' => ['status' => 200, 'body' => 'first'],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('curl_init() called more times than mocked responses. Expected 1 request(s), but attempting request #2');

    $this->runScript('test-curl-multiple', 'tests/Fixtures');
  }

  public function testMockCurlScriptMultipleLessCallsFailure(): void {
    $this->mockCurl([
      'url' => 'https://example.com/first',
      'response' => ['status' => 200, 'body' => 'first'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/second',
      'method' => 'POST',
      'response' => ['status' => 201, 'body' => 'second'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/third',
      'method' => 'PUT',
      'response' => ['status' => 200, 'body' => 'third'],
    ]);

    $this->mockCurl([
      'url' => 'https://example.com/fourth',
      'response' => ['status' => 200, 'body' => 'fourth'],
    ]);

    $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
    $this->expectExceptionMessage('Not all mocked curl responses were consumed. Expected 4 request(s), but only 3 request(s) were made.');

    $this->runScript('test-curl-multiple', 'tests/Fixtures');

    // Manually trigger the check that normally happens in tearDown().
    $this->mockCurlAssertAllMocksConsumed();
  }

  public function testMockCurlScriptFailureArgumentExceptionUrl(): void {
    $this->mockCurl([
      'response' => ['status' => 200],
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Mocked curl response must include "url" key to specify expected URL.');

    $this->runScript('test-curl-get-success', 'tests/Fixtures');
  }

  public function testMockCurlScriptFailureAssertUnexpectedUrl(): void {
    $this->mockCurl([
      'url' => 'https://wrong.com/api',
      'response' => ['status' => 200],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('curl request made to unexpected URL. Expected "https://wrong.com/api", got "https://example.com/api".');

    $this->runScript('test-curl-get-success', 'tests/Fixtures');
  }

}
