# Plan: Comprehensive Curl Function Testing

## 1. Mock Implementation (in MockTrait.php)

**Add mock methods:**
- `mockCurl(array $response)` - Mock single curl call
- `mockCurlMultiple(array $responses)` - Mock multiple curl calls
- `mockCurlAssertAllMocksConsumed()` - Verify all mocks consumed

**Mock response structure:**
```php
[
  'url' => 'https://example.com/api',      // Expected URL
  'method' => 'GET',                        // Optional: expected method
  'response' => [
    'ok' => true,
    'status' => 200,
    'body' => 'response body',
    'error' => null,
    'info' => ['http_code' => 200]
  ]
]
```

**Implementation approach:** Mock the low-level curl_* functions (curl_init, curl_exec, curl_getinfo, etc.) to intercept all curl operations.

## 2. Test Class Structure (tests/Self/MockCurlSelfTest.php)

**Direct Tests (14 tests):**

1. **testMockCurlGetSuccess** - Basic GET with 200
   - Mock: url, status 200, body "success"
   - Assert: ok=true, status=200, body contains "success"

2. **testMockCurlGetCustomSuccess** - GET with custom values
   - Mock: status 201, custom body, custom info
   - Assert: all custom values returned correctly

3. **testMockCurlGetDefaultsSuccess** - GET with minimal mock
   - Mock: only url and status
   - Assert: defaults filled in correctly

4. **testMockCurlGetFailure404** - GET with 404 error
   - Mock: status 404, ok=false
   - Assert: ok=false, status=404

5. **testMockCurlGetFailure500** - GET with 500 error
   - Mock: status 500, ok=false
   - Assert: ok=false, status=500

6. **testMockCurlGetNetworkError** - GET with curl error
   - Mock: error="Could not resolve host"
   - Assert: error message present, ok=false

7. **testMockCurlPostSuccess** - POST with body
   - Mock: POST method, body present, status 201
   - Assert: ok=true, status=201

8. **testMockCurlPostJsonSuccess** - POST with JSON body
   - Mock: POST with JSON content-type header
   - Assert: request handled correctly

9. **testMockCurlRequestCustomMethodSuccess** - PUT/DELETE
   - Mock: method=PUT, status 200
   - Assert: custom method works

10. **testMockCurlMultipleSuccess** - Sequential calls
    - Mock: 3 different responses
    - Assert: each returns correct response in order

11. **testMockCurlMultipleMoreCallsFailure** - Too many calls
    - Mock: 2 responses
    - Make: 3 calls
    - Assert: RuntimeException on 3rd call

12. **testMockCurlMultipleLessCallsFailure** - Too few calls
    - Mock: 3 responses
    - Make: 2 calls
    - Assert: AssertionFailedError in tearDown

13. **testMockCurlFailureArgumentExceptionUrl** - Missing URL validation
    - Mock: response without url key
    - Assert: InvalidArgumentException

14. **testMockCurlFailureAssertUnexpectedUrl** - URL mismatch
    - Mock: url="https://example.com"
    - Call: url="https://different.com"
    - Assert: RuntimeException with clear message

**Script Tests (12 tests):**

15. **testMockCurlScriptGetSuccess** - GET through script (uses test-curl-get-passing)
16. **testMockCurlScriptGetFailure** - GET 404 through script (uses test-curl-get-failing)
17. **testMockCurlScriptGetCustomSuccess** - Custom values through script (uses test-curl-get-passing)
18. **testMockCurlScriptGetDefaultsSuccess** - Defaults through script (uses test-curl-get-passing)
19. **testMockCurlScriptGetNetworkError** - Network error through script (uses test-curl-get-failing)
20. **testMockCurlScriptPostSuccess** - POST success through script (uses test-curl-post-passing)
21. **testMockCurlScriptPostFailure** - POST failure through script (uses test-curl-post-failing)
22. **testMockCurlScriptMultipleSuccess** - Multiple calls through script
23. **testMockCurlScriptMultipleMoreCallsFailure** - Too many calls through script
24. **testMockCurlScriptMultipleLessCallsFailure** - Too few calls through script
25. **testMockCurlScriptFailureArgumentExceptionUrl** - Missing URL through script
26. **testMockCurlScriptFailureAssertUnexpectedUrl** - URL mismatch through script

**Naming Convention:**
- **Test method names**: Use `Success`/`Failure` to indicate the test pathway (what behavior is being tested)
- **Fixture names**: Use `passing`/`failing` to describe the fixture behavior (whether it passes or fails)

## 3. Test Fixtures (tests/Fixtures/)

**test-curl-get-passing:**
```php
#!/usr/bin/env php
<?php
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

**test-curl-get-failing:**
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);
namespace DrevOps\VortexTooling;

require_once __DIR__ . '/../../src/helpers.php';

echo 'Script will call curl_get expecting failure' . PHP_EOL;

$result = curl_get('https://example.com/not-found');

echo 'Response status: ' . $result['status'] . PHP_EOL;
echo 'Response ok: ' . ($result['ok'] ? 'true' : 'false') . PHP_EOL;

if (!$result['ok']) {
  echo 'Request failed as expected' . PHP_EOL;
}
```

**test-curl-post-passing:**
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);
namespace DrevOps\VortexTooling;

require_once __DIR__ . '/../../src/helpers.php';

echo 'Script will call curl_post' . PHP_EOL;

$result = curl_post('https://example.com/api', json_encode(['key' => 'value']), ['Content-Type: application/json']);

echo 'Response status: ' . $result['status'] . PHP_EOL;
echo 'Response ok: ' . ($result['ok'] ? 'true' : 'false') . PHP_EOL;

if ($result['ok']) {
  echo 'POST succeeded' . PHP_EOL;
}
```

**test-curl-post-failing:**
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);
namespace DrevOps\VortexTooling;

require_once __DIR__ . '/../../src/helpers.php';

echo 'Script will call curl_post expecting failure' . PHP_EOL;

$result = curl_post('https://example.com/error', json_encode(['key' => 'value']), ['Content-Type: application/json']);

echo 'Response status: ' . $result['status'] . PHP_EOL;
echo 'Response ok: ' . ($result['ok'] ? 'true' : 'false') . PHP_EOL;

if (!$result['ok']) {
  echo 'POST failed as expected' . PHP_EOL;
}
```

**test-curl-multiple:**
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);
namespace DrevOps\VortexTooling;

require_once __DIR__ . '/../../src/helpers.php';

echo 'Script will call curl multiple times' . PHP_EOL;

$result1 = curl_get('https://example.com/first');
echo 'First call status: ' . $result1['status'] . PHP_EOL;

$result2 = curl_post('https://example.com/second', 'data');
echo 'Second call status: ' . $result2['status'] . PHP_EOL;

$result3 = curl_request('https://example.com/third', ['method' => 'PUT']);
echo 'Third call status: ' . $result3['status'] . PHP_EOL;

echo 'Script completed' . PHP_EOL;
```

## 4. Implementation Order

1. **Step 1:** Implement `mockCurl*` methods in MockTrait.php
2. **Step 2:** Create test fixtures (5 files: get-passing, get-failing, post-passing, post-failing, multiple)
3. **Step 3:** Implement direct tests (14 tests)
4. **Step 4:** Implement script tests (12 tests)
5. **Step 5:** Run all Self tests to verify no conflicts

## 5. Key Patterns to Follow

- **Same as passthru:** Queue-based response system with index tracking
- **Validation:** Require 'url' key in mock response (like 'cmd' for passthru)
- **Teardown:** Auto-verify all mocks consumed
- **Error messages:** Clear, descriptive messages for mismatches
- **No process isolation:** Should work without it (like passthru)

**Total:** 26 tests covering positive/negative scenarios, direct/script execution, single/multiple calls, and all three curl methods (get, post, request).
