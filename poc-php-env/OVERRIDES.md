# Script Override System

## The Problem

You ship default scripts, but users want to customize them without modifying your code.

## The Solution

Scripts can be overridden by placing custom versions in a separate directory.

```
default-scripts/
  ├── task-alpha     (shipped default)
  └── task-beta      (shipped default)

custom-scripts/      (user's custom directory)
  ├── task-alpha     (overrides default)
  └── task-beta      (overrides default)
```

## How It Works

Set `OVERRIDE_DIR` environment variable:
```bash
export OVERRIDE_DIR=/path/to/custom-scripts
```

When calling a script, the system checks:
1. Does `$OVERRIDE_DIR/script-name` exist?
2. If YES → use custom version
3. If NO → use default version

## Two Approaches

### Option 1: Script Self-Override (Recommended)

**The script checks if it should be overridden:**

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../override-helpers.php';

// Check if custom version exists - if yes, execute it and exit
execute_override_if_exists(basename(__FILE__));

// If we're still here, no override exists - run default code
echo "Running default implementation\n";
```

**Pros:**
- Scripts are self-aware
- Works regardless of how they're called
- User just needs to set `OVERRIDE_DIR`

**Cons:**
- Each script needs the override check

### Option 2: Caller Checks Override

**The caller checks for overrides before calling:**

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/override-helpers.php';

// Caller decides which version to execute
execute_with_override('./default-scripts/task-beta', $exit_code);
```

**Pros:**
- Default scripts don't need to know about overrides
- Centralized override logic
- Works with scripts you don't control

**Cons:**
- Only works if called through this mechanism
- Direct calls bypass override system

## Usage Example

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/override-helpers.php';

// Set where custom scripts live
putenv('OVERRIDE_DIR=' . __DIR__ . '/custom-scripts');

// Option 1: Script checks itself
passthru('./default-scripts/task-alpha');
// → Runs custom-scripts/task-alpha if it exists

// Option 2: Caller checks
execute_with_override('./default-scripts/task-beta', $exit_code);
// → Runs custom-scripts/task-beta if it exists
```

## Helper Functions

### `execute_override_if_exists($script_name)`

Add to the START of any script that should be overrideable:

```php
require_once __DIR__ . '/../override-helpers.php';
execute_override_if_exists(basename(__FILE__));
// If override exists, it runs and this script exits
// If no override, execution continues
```

### `execute_with_override($script_path, &$exit_code)`

Use when calling a script:

```php
execute_with_override('./default-scripts/task-beta', $exit_code);
// Automatically uses override if available
```

### `get_override_path($script_name)`

Get the path to override (if it exists):

```php
$override = get_override_path('my-script');
if ($override) {
  echo "Override found: $override\n";
}
```

## Real-World Example: Vortex

**Shipped scripts:**
```
scripts/vortex/
  ├── provision-db
  ├── provision-content
  └── provision-config
```

**User customization:**
```bash
# In .env or environment
export OVERRIDE_DIR=./scripts/custom
```

**User's custom scripts:**
```
scripts/custom/
  └── provision-db    # Only override DB provisioning
```

**Result:**
- `provision-db` → uses custom version
- `provision-content` → uses default (no override)
- `provision-config` → uses default (no override)

## Testing the POC

```bash
# Run the demo
./caller-with-overrides
```

You'll see:
- `task-alpha` uses CUSTOM version (override exists)
- `task-beta` uses CUSTOM version (override exists)
- When `OVERRIDE_DIR` is unset, defaults are used

## Which Option to Use?

**Use Option 1 (Self-Override) if:**
- You control the scripts
- Scripts might be called from multiple places
- You want maximum flexibility

**Use Option 2 (Caller Checks) if:**
- You don't control the scripts
- You want centralized override logic
- Scripts are always called through your code

**Or use BOTH:**
- Shipped scripts use Option 1 (self-check)
- Callers also use Option 2 (belt and suspenders)
- Overrides work no matter how scripts are called
