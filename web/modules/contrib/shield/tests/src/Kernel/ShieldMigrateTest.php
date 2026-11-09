<?php

namespace Drupal\Tests\shield\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests shield config migration.
 *
 * This deliberately extends MigrateTestBase rather than migrate_drupal's
 * MigrateDrupal7TestBase: Drupal 12 removed migrate_drupal, and a test class
 * whose parent cannot be loaded breaks the whole test run there instead of
 * being skipped.
 *
 * @group shield
 */
#[Group('shield')]
#[RunTestsInSeparateProcesses]
class ShieldMigrateTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate_drupal',
    'path_alias',
    'shield',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Drupal 12 removed migrate_drupal, and with it the Drupal 7 'variable'
    // source plugin that migrations/shield_settings.yml uses. It is not moving
    // to contrib, so there is nothing to migrate with there. Remove this test
    // and the migration once the module drops Drupal 11.
    if (!class_exists('Drupal\migrate_drupal\Plugin\migrate\source\Variable')) {
      $this->markTestSkipped('Drupal 7 migrations need migrate_drupal, which this version of Drupal core does not provide.');
    }
    parent::setUp();

    $default_db = Database::getConnection()->getKey();
    Database::setActiveConnection($this->sourceDatabase->getKey());
    require $this->getFixtureFilePath();
    Database::setActiveConnection($default_db);
  }

  /**
   * Gets the path to the Drupal 7 database fixture.
   *
   * @return string
   *   The path to the fixture file.
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      $this->container->get('extension.list.module')->getPath('shield'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Asserts that shield configuration is migrated.
   */
  public function testShieldMigration() {
    $expected_config = [
      'langcode' => 'en',
      'shield_enable' => TRUE,
      'credentials' => [
        'shield' => [
          'user' => 'abc',
          'pass' => '1234',
        ],
      ],
      'allow_cli' => FALSE,
      'print' => 'Authenticate!',
      'method' => 0,
      'paths' => '/node/3
/blog/*',
      'allowlist' => '192.168.0.75',
    ];

    $this->executeMigration('shield_settings');
    $config = $this->config('shield.settings')->getRawData();
    $this->assertSame($expected_config, $config);
  }

}
