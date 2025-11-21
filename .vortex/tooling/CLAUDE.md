# Vortex Tooling Package Development Guide

This document describes the Vortex tooling package - a collection of PHP helper functions and scripts for Vortex notification and utility operations.

## Package Overview

**Location**: `.vortex/tooling/`

**Purpose**: Provides reusable PHP functions for Vortex scripts, including:
- Environment variable loading
- Output formatting (info, task, pass, fail, note)
- Variable and command validation
- HTTP requests (curl wrappers)
- Token replacement utilities

**Key Principle**: This is a **standalone PHP package** that can be used by Vortex scripts without requiring Drupal or Composer dependencies at runtime.

## Project Structure

```text
.vortex/tooling/
├── src/
│   └── helpers.php              # Core helper functions
├── tests/
│   ├── Fixtures/                # Test fixture scripts
│   │   ├── test-passthru-*      # Passthru test fixtures
│   │   ├── test-quit-*          # Quit test fixtures
│   │   ├── test-curl-*          # Curl test fixtures
│   │   └── test-exit-*          # Exit test fixtures
│   ├── Self/                    # Self-tests for mock infrastructure
│   │   ├── MockPassthruSelfTest.php
│   │   ├── MockQuitSelfTest.php
│   │   └── MockCurlSelfTest.php
│   ├── Unit/                    # Unit tests
│   │   ├── UnitTestCase.php     # Base test case
│   │   ├── ExitException.php    # Exit exception for testing
│   │   ├── ExitMockTest.php     # Exit mocking tests
│   │   ├── EnvTest.php          # Environment tests
│   │   └── SelfTest.php         # Self-tests for core functions
│   └── Traits/
│       └── MockTrait.php        # Mock infrastructure (passthru, quit, curl)
├── composer.json                # Dev dependencies only
├── phpunit.xml                  # PHPUnit configuration
└── CLAUDE.md                    # This file
```

## Core Helper Functions

### Environment Management

```php
load_dotenv(array $env_files = ['.env']): void
```
Loads environment variables from .env files. Supports quoted values and comments.

### Output Formatters

```php
note(string $format, ...$args): void      // Plain note output
task(string $format, ...$args): void      // [TASK] Blue output
info(string $format, ...$args): void      // [INFO] Cyan output
pass(string $format, ...$args): void      // [ OK ] Green output
fail_no_exit(string $format, ...$args): void  // [FAIL] Red output (no exit)
fail(string $format, ...$args): void      // [FAIL] Red output + exit(1)
```

### Validation Functions

```php
validate_variable(string $name, ?string $message = NULL): void
validate_command(string $command): void
```

### HTTP Request Functions

```php
curl_get(string $url, array $headers = [], int $timeout = 10): array
curl_post(string $url, $body = NULL, array $headers = [], int $timeout = 10): array
curl_request(string $url, array $options = []): array
```

**Return Format**:
```php
[
  'ok' => bool,           // TRUE if HTTP < 400
  'status' => int,        // HTTP status code
  'body' => string|false, // Response body
  'error' => ?string,     // cURL error message
  'info' => array,        // cURL info array
]
```

### Utility Functions

```php
replace_tokens(string $template, array $replacements): string
is_debug(): bool
quit(int $code = 0): void  // Wrapper around exit() for testing
```

## Testing Architecture

### Test Organization

The package uses **three types of tests**:

1. **Unit Tests** (`tests/Unit/`) - Test individual helper functions
2. **Self Tests** (`tests/Self/`) - Test the mock infrastructure itself
3. **Fixture Scripts** (`tests/Fixtures/`) - External scripts for integration testing

### Mock System (MockTrait.php)

The package provides a comprehensive mocking system for testing scripts that use:
- `passthru()` - Shell command execution
- `quit()` / `exit()` - Script termination
- `curl_*()` - HTTP requests

#### Mock Architecture

**Key Principle**: Use queue-based mock responses that are consumed sequentially.

Each mock system maintains:
- `$mock[Function]Responses` - Array of queued responses
- `$mock[Function]Index` - Current response index
- `$mock[Function]Checked` - Flag to prevent duplicate teardown checks

**Pattern**:
```php
protected function mockPassthru(array $response): void
protected function mockPassthruMultiple(array $responses): void
protected function mockPassthruAssertAllMocksConsumed(): void

protected function mockQuit(int $code = 0): void

protected function mockCurl(array $response): void
protected function mockCurlMultiple(array $responses): void
protected function mockCurlAssertAllMocksConsumed(): void
```

#### Passthru Mocking

**Response Structure**:
```php
[
  'cmd' => 'echo "hello"',        // Required: Expected command
  'output' => 'command output',   // Optional: Output to echo
  'result_code' => 0,             // Optional: Exit code (default: 0)
  'return' => NULL,               // Optional: Return value (NULL or FALSE)
]
```

**Example**:
```php
$this->mockPassthru([
  'cmd' => 'ls -la',
  'output' => 'file.txt',
  'result_code' => 0,
]);

passthru('ls -la', $exit_code);  // Returns mocked output
```

#### Quit Mocking

**Behavior**: Throws exceptions instead of exiting.

- Exit code 0 → `QuitSuccessException`
- Exit code != 0 → `QuitErrorException`

**Example**:
```php
$this->mockQuit(1);

$this->expectException(QuitErrorException::class);
$this->expectExceptionCode(1);

\DrevOps\VortexTooling\quit(1);  // Throws exception
```

#### Curl Mocking

**Response Structure**:
```php
[
  'url' => 'https://example.com/api',      // Required: Expected URL
  'method' => 'POST',                       // Optional: Expected HTTP method
  'response' => [
    'ok' => true,                           // Optional: Success flag (default: TRUE)
    'status' => 200,                        // Required: HTTP status code
    'body' => 'response body',              // Optional: Response body (default: '')
    'error' => null,                        // Optional: cURL error (default: NULL)
    'info' => ['http_code' => 200],        // Optional: cURL info (default: [])
  ],
]
```

**Example**:
```php
$this->mockCurl([
  'url' => 'https://api.example.com/data',
  'method' => 'POST',
  'response' => [
    'status' => 201,
    'body' => '{"id": 123}',
  ],
]);

$result = curl_post('https://api.example.com/data', '{"data":"value"}');
// Returns mocked response
```

**Implementation Details**:

The curl mock intercepts low-level curl functions:
- `curl_init()` - Stores URL, returns mock handle
- `curl_setopt_array()` - Extracts HTTP method from options
- `curl_exec()` - Validates URL/method, returns mocked body
- `curl_errno()` - Returns 0 or 1 based on error presence
- `curl_error()` - Returns mocked error message
- `curl_getinfo()` - Returns mocked info array with http_code
- `curl_close()` - Increments index, resets state

### Resource Management

**Critical**: `curl_request()` uses try-finally to ensure cleanup:

```php
function curl_request(string $url, array $options = []): array {
  $ch = curl_init($url);

  try {
    // All curl operations here
    return [...];
  }
  finally {
    // Always close the curl handle, even if an exception occurs.
    curl_close($ch);
  }
}
```

This ensures that:
- curl_close() always runs (incrementing mock index)
- Exception tests don't report unconsumed mocks
- No manual mock tracking needed in tests

## Test Naming Conventions

### Critical Pattern Rules

**Direct Tests** (testing functions directly):
```php
testMock[Function][Description][Outcome]
```

**Script Tests** (testing through fixture scripts):

For **single-function** mocks (passthru, quit):
```php
testMock[Function]Script[Description][Outcome]
```

For **multi-function** mocks (curl with get/post/request):
```php
testMock[Function][Method]Script[Description][Outcome]
```

### Examples

**Passthru Tests**:
- ✅ `testMockPassthruSuccess` (direct)
- ✅ `testMockPassthruScriptPassingSuccess` (script)
- ✅ `testMockPassthruScriptFailingSuccess` (script)

**Quit Tests**:
- ✅ `testMockQuit0Success` (direct, exit code in name)
- ✅ `testMockQuitScript0Success` (script, exit code in name)

**Curl Tests**:
- ✅ `testMockCurlGetSuccess` (direct)
- ✅ `testMockCurlGetScriptPassingSuccess` (script - method before "Script")
- ✅ `testMockCurlPostScriptPassingSuccess` (script - method before "Script")
- ✅ `testMockCurlMultipleScriptSuccess` (script - testing multiple calls)

**Why method comes before "Script" for curl**:
- Single function: `testMockPassthruScript...` (no ambiguity)
- Multiple functions: `testMockCurlGetScript...` (disambiguates which curl function)

### Naming Convention Components

- **[Function]**: Passthru, Quit, Curl, CurlGet, CurlPost, CurlMultiple
- **[Method]**: Get, Post (for curl tests only)
- **[Description]**: Passing, Failing, Custom, Defaults, NetworkError, Multiple, MoreCalls, LessCalls, etc.
- **[Outcome]**: Success (test passes), Failure (test expects failure)

**Success vs Failure**:
- `Success` - Test pathway succeeds (expected behavior occurs)
- `Failure` - Test pathway fails (testing error conditions)

**Passing vs Failing** (fixtures only):
- `test-curl-get-passing` - Fixture that expects successful execution
- `test-curl-get-failing` - Fixture that expects failure execution

## Test Fixtures

### Fixture Naming Convention

Pattern: `test-[function]-[behavior]`

Examples:
- `test-passthru-passing` - Passthru script expecting success
- `test-passthru-failing` - Passthru script expecting failure
- `test-quit-success` - Quit script with exit code 0
- `test-quit-failure` - Quit script with exit code 1
- `test-curl-get-passing` - GET request expecting success
- `test-curl-get-failing` - GET request expecting failure
- `test-curl-post-passing` - POST request expecting success
- `test-curl-post-failing` - POST request expecting failure
- `test-curl-multiple` - Multiple curl calls

### Fixture Structure

All fixtures must:
1. Start with shebang: `#!/usr/bin/env php`
2. Use strict types: `declare(strict_types=1);`
3. Use namespace: `namespace DrevOps\VortexTooling;`
4. Load helpers: `require_once __DIR__ . '/../../src/helpers.php';`
5. Print output for test assertions
6. Be executable: `chmod +x tests/Fixtures/test-*`

**Example**:
```php
#!/usr/bin/env php
<?php

/**
 * @file
 * GET request fixture for curl_get testing.
 */

declare(strict_types=1);

namespace DrevOps\VortexTooling;

require_once __DIR__ . '/../../src/helpers.php';

echo 'Script will call curl_get' . PHP_EOL;

$result = curl_get('https://example.com/api');

echo 'Response status: ' . $result['status'] . PHP_EOL;
echo 'Response ok: ' . ($result['ok'] ? 'true' : 'false') . PHP_EOL;

if ($result['ok']) {
  echo 'Response body: ' . $result['body'] . PHP_EOL;
}
```

## Development Workflow

### Adding New Helper Functions

1. **Add to src/helpers.php**:
```php
/**
 * Function description.
 *
 * @param string $param
 *   Parameter description.
 *
 * @return mixed
 *   Return description.
 */
function my_new_function(string $param) {
  // Implementation
}
```

2. **Create unit test in tests/Unit/**:
```php
class MyNewFunctionTest extends UnitTestCase {
  public function testMyNewFunctionSuccess(): void {
    $result = \DrevOps\VortexTooling\my_new_function('input');
    $this->assertEquals('expected', $result);
  }
}
```

3. **Run tests**:
```bash
./vendor/bin/phpunit tests/Unit/MyNewFunctionTest.php
```

### Adding New Mock Functionality

1. **Add to MockTrait.php**:
```php
protected function mockMyFunction(array $response): void {
  $this->mockMyFunctionMultiple([$response]);
}

protected function mockMyFunctionMultiple(array $responses): void {
  // Store responses
  $this->mockMyFunctionResponses = array_merge(
    $this->mockMyFunctionResponses,
    $responses
  );

  // Create mock if not exists
  if ($this->mockMyFunction === NULL) {
    $this->mockMyFunction = $this->getFunctionMock('DrevOps\\VortexTooling', 'my_function');
    $this->mockMyFunction
      ->expects($this->any())
      ->willReturnCallback(function () {
        // Mock implementation
      });
  }
}

protected function mockMyFunctionAssertAllMocksConsumed(): void {
  // Teardown validation
}
```

2. **Add to mockTearDown()**:
```php
protected function mockTearDown(): void {
  // ... existing teardown ...

  // Add new mock teardown
  $this->mockMyFunctionAssertAllMocksConsumed();
  $this->mockMyFunction = NULL;
  $this->mockMyFunctionResponses = [];
  $this->mockMyFunctionIndex = 0;
  $this->mockMyFunctionChecked = FALSE;
}
```

3. **Create self-tests in tests/Self/**:
- Direct tests (14+ tests)
- Script tests (12+ tests)
- Follow naming conventions

4. **Create fixtures in tests/Fixtures/**:
- Passing fixture
- Failing fixture
- Multiple calls fixture (if applicable)

### Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Specific test suite
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Self/

# Specific test file
./vendor/bin/phpunit tests/Self/MockCurlSelfTest.php

# With testdox output
./vendor/bin/phpunit --testdox

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## Common Patterns

### Multiple Mock Calls

```php
// Queue multiple responses
$this->mockCurl([
  'url' => 'https://example.com/first',
  'response' => ['status' => 200, 'body' => 'first'],
]);

$this->mockCurl([
  'url' => 'https://example.com/second',
  'response' => ['status' => 200, 'body' => 'second'],
]);

// Both calls return mocked responses in order
$result1 = curl_get('https://example.com/first');
$result2 = curl_get('https://example.com/second');
```

### Testing Too Many Calls

```php
// Mock only 1 response
$this->mockCurl([
  'url' => 'https://example.com/api',
  'response' => ['status' => 200],
]);

// Expect exception on second call
$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('curl_init() called more times than mocked responses. Expected 1 request(s), but attempting request #2');

curl_get('https://example.com/api');  // OK
curl_get('https://example.com/api');  // Throws RuntimeException
```

### Testing Too Few Calls

```php
// Mock 3 responses but only make 2 calls
$this->mockCurl(['url' => 'https://example.com/1', 'response' => ['status' => 200]]);
$this->mockCurl(['url' => 'https://example.com/2', 'response' => ['status' => 200]]);
$this->mockCurl(['url' => 'https://example.com/3', 'response' => ['status' => 200]]);

curl_get('https://example.com/1');
curl_get('https://example.com/2');

// Expect assertion failure in tearDown
$this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
$this->expectExceptionMessage('Not all mocked curl responses were consumed. Expected 3 request(s), but only 2 request(s) were made.');

// Manually trigger teardown check
$this->mockCurlAssertAllMocksConsumed();
```

### Testing Validation Errors

```php
// Missing required field
$this->mockCurl([
  'response' => ['status' => 200],  // Missing 'url'
]);

$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Mocked curl response must include "url" key to specify expected URL.');

curl_get('https://example.com/api');
```

### Testing URL Mismatch

```php
// Mock expects different URL
$this->mockCurl([
  'url' => 'https://expected.com/api',
  'response' => ['status' => 200],
]);

$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('curl request made to unexpected URL. Expected "https://expected.com/api", got "https://actual.com/api".');

curl_get('https://actual.com/api');  // URL mismatch
```

### Script Testing with runScript()

```php
// Mock the function
$this->mockCurl([
  'url' => 'https://example.com/api',
  'response' => ['status' => 200, 'body' => 'success'],
]);

// Run fixture script
$output = $this->runScript('test-curl-get-passing', 'tests/Fixtures');

// Assert on script output
$this->assertStringContainsString('Response status: 200', $output);
$this->assertStringContainsString('Response ok: true', $output);
$this->assertStringContainsString('Response body: success', $output);
```

## Debugging

### Enable Debug Output

```bash
# In tests
TEST_VORTEX_DEBUG=1 ./vendor/bin/phpunit

# In scripts
VORTEX_DEBUG=1 php script.php
```

### Common Issues

**Issue**: "Function already enabled" error

**Cause**: Trying to create multiple mocks for the same function

**Solution**: Use `mockMultiple()` method to queue responses, don't create new mocks:
```php
// ❌ Wrong
$this->mockCurl([...]);
$this->mockCurl([...]);  // Error

// ✅ Correct
$this->mockCurl([...]);  // Creates mock + queues first response
$this->mockCurl([...]);  // Just queues second response (mock already exists)
```

**Issue**: "Not all mocked responses were consumed"

**Cause**: Mocked more responses than actual function calls

**Solution**: Ensure test makes all expected function calls

**Issue**: Test hangs waiting for input

**Cause**: Script requires interactive input and wasn't mocked

**Solution**: Mock the interactive function call

## Best Practices

### DO

- ✅ Use queue-based mocking for sequential calls
- ✅ Validate all required fields in mock responses
- ✅ Test both success and error pathways
- ✅ Use descriptive test names following conventions
- ✅ Create fixtures for integration testing
- ✅ Always validate that all mocks are consumed
- ✅ Use try-finally for resource cleanup
- ✅ Test too many calls, too few calls, and mismatches

### DON'T

- ❌ Create new mocks for each call (use queue pattern)
- ❌ Skip teardown validation
- ❌ Mix direct and script tests in same method
- ❌ Forget to load helpers.php in setUp()
- ❌ Use inconsistent naming conventions
- ❌ Create fixtures without proper namespace
- ❌ Test without asserting on output

## Reference

### PHPUnit Attributes

```php
#[CoversClass(UnitTestCase::class)]  // Coverage declaration
```

### UnitTestCase Methods

```php
protected function setUp(): void                           // Setup before each test
protected function tearDown(): void                        // Cleanup after each test
protected function runScript(string $name, string $dir)   // Run fixture script
```

### MockTrait Methods

```php
// Passthru
protected function mockPassthru(array $response): void
protected function mockPassthruMultiple(array $responses): void
protected function mockPassthruAssertAllMocksConsumed(): void

// Quit
protected function mockQuit(int $code = 0): void

// Curl
protected function mockCurl(array $response): void
protected function mockCurlMultiple(array $responses): void
protected function mockCurlAssertAllMocksConsumed(): void

// Teardown
protected function mockTearDown(): void
```

## Additional Resources

- PHPUnit Documentation: https://phpunit.de/documentation.html
- php-mock Documentation: https://github.com/php-mock/php-mock-phpunit
- Vortex Template: https://github.com/drevops/vortex

---

*This documentation should be updated whenever significant changes are made to the tooling package structure, mock system, or testing conventions.*
