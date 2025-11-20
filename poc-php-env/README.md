# PHP Binary Calls POC

## The Answer

**YES** - PHP scripts can call other scripts (PHP or bash) as binaries and pass environment variables using `putenv()`.

## Run It

```bash
# Basic test
./caller

# Exit code handling test
./caller-exit-codes

# Execution functions comparison
./caller-exec-functions

# Script override system
./caller-with-overrides
```

## Files

- `caller` - PHP script that sets `MY_VAR=hello` and calls both callees
- `caller-exit-codes` - Shows how caller handles exit codes from callees
- `callee-php` - PHP script that prints `MY_VAR`
- `callee-bash` - Bash script that prints `MY_VAR`
- `callee-success` - Exits with code 0
- `callee-fail` - Exits with code 1

## The Pattern

**Caller:**
```php
#!/usr/bin/env php
<?php
putenv('MY_VAR=hello');
passthru('./callee-php');  // No extension, no "php" command
```

**Callee (PHP):**
```php
#!/usr/bin/env php
<?php
echo getenv('MY_VAR');  // Prints: hello
```

**Callee (Bash):**
```bash
#!/bin/bash
echo "$MY_VAR"  # Prints: hello
```

## Exit Code Handling

**Caller catches exit codes:**
```php
#!/usr/bin/env php
<?php
$exit_code = 0;
passthru('./callee-fail', $exit_code);

if ($exit_code !== 0) {
  echo "Callee failed with code $exit_code\n";
  exit($exit_code);  // Stop and pass code up
}
```

**Callee returns exit code:**
```php
#!/usr/bin/env php
<?php
exit(1);  // Caller receives this code
```

## Execution Functions Comparison

| Function       | Streams to screen | Captures output   | Gets exit code | Complexity |
|----------------|-------------------|-------------------|----------------|------------|
| `passthru()`   | ✅ YES             | ❌ NO              | ✅ YES          | Simple     |
| `system()`     | ✅ YES             | ⚠️ Last line only | ✅ YES          | Simple     |
| `exec()`       | ❌ NO              | ✅ YES (array)     | ✅ YES          | Simple     |
| `shell_exec()` | ❌ NO              | ✅ YES (string)    | ❌ NO           | Simple     |
| `proc_open()`  | ✅ YES*            | ✅ YES             | ✅ YES          | Complex    |

*Requires manual stream handling

**Recommendations:**
- **Just stream output:** Use `passthru()`
- **Just capture output:** Use `exec()` (array) or `shell_exec()` (string)
- **Stream AND capture:** Use `proc_open()` (complex but powerful)

**Example - Stream AND capture with proc_open():**
```php
$process = proc_open('./script', $descriptors, $pipes);
stream_set_blocking($pipes[1], false);

$captured = '';
while (!feof($pipes[1])) {
  $line = fgets($pipes[1]);
  if ($line !== false) {
    echo $line;        // Stream to screen
    $captured .= $line; // Capture to variable
  }
}
$exit_code = proc_close($process);
```

## Script Override System

**Problem:** Ship default scripts but allow users to customize them.

**Solution:** Users place custom scripts in `$OVERRIDE_DIR` with same name.

```
default-scripts/task-alpha  →  custom-scripts/task-alpha (overrides)
```

**Usage:**

```php
putenv('OVERRIDE_DIR=/path/to/custom');

// Option 1: Script checks itself
require_once 'override-helpers.php';
execute_override(basename(__FILE__));
// Continues if no override exists

// Option 2: Caller checks
execute_with_override('./default-scripts/task-alpha', $exit_code);
// Automatically uses override if available
```

**See:** `OVERRIDES.md` for detailed documentation and examples.

Done. It works.
