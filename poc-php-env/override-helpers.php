<?php

/**
 * @file
 * Helper functions for script override system.
 */

/**
 * Get the override path for a script.
 *
 * @param string $script_name
 *   Script name (e.g., 'provision-db')
 *
 * @return string|null
 *   Full path to override script if it exists, NULL otherwise.
 */
function get_override_path($script_name) {
  $override_dir = getenv('OVERRIDE_DIR');

  if (!$override_dir) {
    return null;
  }

  $override_path = $override_dir . '/' . $script_name;

  if (file_exists($override_path) && is_executable($override_path)) {
    return $override_path;
  }

  return null;
}

/**
 * Check if current script has an override and execute it if found.
 *
 * Call this at the start of your script to allow it to be overridden.
 *
 * @param string $script_name
 *   Name of current script.
 *
 * @return void
 *   Exits if override is found and executed.
 */
function execute_override_if_exists($script_name) {
  $override_path = get_override_path($script_name);

  if ($override_path) {
    echo "[OVERRIDE] Using custom script: $override_path\n";
    passthru("\"$override_path\"", $exit_code);
    exit($exit_code);
  }
}

/**
 * Execute a script, using override if available.
 *
 * @param string $script_path
 *   Path to the default script.
 * @param int &$exit_code
 *   Exit code from the script.
 *
 * @return void
 */
function execute_with_override($script_path, &$exit_code) {
  $script_name = basename($script_path);
  $override_path = get_override_path($script_name);

  if ($override_path) {
    echo "[OVERRIDE] Using custom script: $override_path\n";
    passthru("\"$override_path\"", $exit_code);
  } else {
    echo "[DEFAULT] Using default script: $script_path\n";
    passthru("\"$script_path\"", $exit_code);
  }
}
