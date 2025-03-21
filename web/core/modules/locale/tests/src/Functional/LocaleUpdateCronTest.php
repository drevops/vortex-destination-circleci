<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional;

use Drupal\Core\Database\Database;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\locale\Hook\LocaleHooks;

/**
 * Tests for using cron to update project interface translations.
 *
 * @group locale
 */
class LocaleUpdateCronTest extends LocaleUpdateBase {

  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $admin_user = $this->drupalCreateUser([
      'administer modules',
      'administer site configuration',
      'administer languages',
      'access administration pages',
      'translate interface',
    ]);
    $this->drupalLogin($admin_user);
    $this->addLanguage('de');
  }

  /**
   * Tests interface translation update using cron.
   */
  public function testUpdateCron(): void {
    // Set a flag to let the locale_test module replace the project data with a
    // set of test projects.
    \Drupal::state()->set('locale.test_projects_alter', TRUE);

    // Setup local and remote translations files.
    $this->setTranslationFiles();
    $this->config('locale.settings')->set('translation.default_filename', '%project-%version.%language._po')->save();

    // Update translations using batch to ensure a clean test starting point.
    $this->drupalGet('admin/reports/translations/check');
    $this->drupalGet('admin/reports/translations');
    $this->submitForm([], 'Update translations');

    // Store translation status for comparison.
    $initial_history = locale_translation_get_file_history();

    // Prepare for test: Simulate new translations being available.
    // Change the last updated timestamp of a translation file.
    $contrib_module_two_uri = 'public://local/contrib_module_two-8.x-2.0-beta4.de._po';
    touch(\Drupal::service('file_system')->realpath($contrib_module_two_uri), \Drupal::time()->getRequestTime());

    // Prepare for test: Simulate that the file has not been checked for a long
    // time. Set the last_check timestamp to zero.
    $query = Database::getConnection()->update('locale_file');
    $query->fields(['last_checked' => 0]);
    $query->condition('project', 'contrib_module_two');
    $query->condition('langcode', 'de');
    $query->execute();

    // Test: Disable cron update and verify that no tasks are added to the
    // queue.
    $edit = [
      'update_interval_days' => 0,
    ];
    $this->drupalGet('admin/config/regional/translate/settings');
    $this->submitForm($edit, 'Save configuration');

    // Execute locale cron tasks to add tasks to the queue.
    $localeCron = new LocaleHooks();
    $localeCron->cron();

    // Check whether no tasks are added to the queue.
    $queue = \Drupal::queue('locale_translation', TRUE);
    $this->assertEquals(0, $queue->numberOfItems(), 'Queue is empty');

    // Test: Enable cron update and check if update tasks are added to the
    // queue.
    // Set cron update to Weekly.
    $edit = [
      'update_interval_days' => 7,
    ];
    $this->drupalGet('admin/config/regional/translate/settings');
    $this->submitForm($edit, 'Save configuration');

    // Execute locale cron tasks to add tasks to the queue.
    $localeCron->cron();

    // Check whether tasks are added to the queue.
    // Expected tasks:
    // - locale_translation_batch_version_check
    // - locale_translation_batch_status_check
    // - locale_translation_batch_status_finished.
    $queue = \Drupal::queue('locale_translation', TRUE);
    $this->assertEquals(3, $queue->numberOfItems(), 'Queue holds tasks for one project.');
    $item = $queue->claimItem();
    $queue->releaseItem($item);
    $this->assertEquals('contrib_module_two', $item->data[1][0], 'Queue holds tasks for contrib module one.');

    // Test: Run cron for a second time and check if tasks are not added to
    // the queue twice.
    $localeCron->cron();

    // Check whether no more tasks are added to the queue.
    $queue = \Drupal::queue('locale_translation', TRUE);
    $this->assertEquals(3, $queue->numberOfItems(), 'Queue holds tasks for one project.');

    // Ensure last checked is updated to a greater time than the initial value.
    sleep(1);
    // Test: Execute cron and check if tasks are executed correctly.
    // Run cron to process the tasks in the queue.
    $this->cronRun();

    drupal_static_reset('locale_translation_get_file_history');
    $history = locale_translation_get_file_history();
    $initial = $initial_history['contrib_module_two']['de'];
    $current = $history['contrib_module_two']['de'];
    // Verify that the translation of contrib_module_one is imported and
    // updated.
    $this->assertGreaterThan($initial->timestamp, $current->timestamp);
    $this->assertGreaterThan($initial->last_checked, $current->last_checked);
  }

}
