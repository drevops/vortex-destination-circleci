<?php

/**
 * @file
 * Post-update hooks for the Environment Indicator module.
 */

/**
 * Fix URLs in configuration by removing trailing slashes.
 */
function environment_indicator_post_update_remove_trailing_slashes(&$sandbox) {
  $config_factory = \Drupal::configFactory();

  // Initialize sandbox.
  if (!isset($sandbox['config_names'])) {
    // Load all config names for the environment_indicator.switcher.* type.
    $sandbox['config_names'] = $config_factory
      ->listAll('environment_indicator.switcher');
    $sandbox['total'] = count($sandbox['config_names']);
    $sandbox['processed'] = 0;
  }

  // Check if there are configurations left to process.
  if (empty($sandbox['config_names'])) {
    return t('No environment switcher configurations to process.');
  }

  // Process configurations in batches.
  $batch_size = 10;
  $config_names = array_splice($sandbox['config_names'], 0, $batch_size);

  foreach ($config_names as $config_name) {
    $config = $config_factory->getEditable($config_name);
    $url = $config->get('url');

    if (!empty($url)) {
      // Remove trailing slash from the URL.
      $updated_url = rtrim($url, '/');
      if ($url !== $updated_url) {
        $config->set('url', $updated_url);
        $config->save();
      }
    }

    $sandbox['processed']++;
  }

  // Update progress.
  if ($sandbox['processed'] < $sandbox['total']) {
    $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];
    return t('Processed @processed out of @total environment switcher configurations.', [
      '@processed' => $sandbox['processed'],
      '@total' => $sandbox['total'],
    ]);
  }
  else {
    $sandbox['#finished'] = 1;
    return t('All environment switcher configurations have been updated.');
  }
}
