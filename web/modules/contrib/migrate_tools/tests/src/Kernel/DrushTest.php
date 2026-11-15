<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_tools\Kernel {

  use Drupal\migrate\Event\MigrateEvents;
  use Drupal\migrate\Plugin\MigrationInterface;
  use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
  use Consolidation\OutputFormatters\FormatterManager;
  use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
  use Drupal\migrate_tools\Drush\Commands\MigrateFieldsSourceCommand;
  use Drupal\migrate_tools\Drush\Commands\MigrateImportCommand;
  use Drupal\migrate_tools\Drush\Commands\MigrateMessagesCommand;
  use Drupal\migrate_tools\Drush\Commands\MigrateResetStatusCommand;
  use Drupal\migrate_tools\Drush\Commands\MigrateRollbackCommand;
  use Drupal\migrate_tools\Drush\Commands\MigrateStatusCommand;
  use Drupal\migrate_tools\Drush\Commands\MigrateStopCommand;
  use Drupal\Tests\migrate\Kernel\MigrateTestBase;
  use Drupal\user\Entity\User;
  use PHPUnit\Framework\Attributes\Group;
  use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
  use Psr\Log\LoggerInterface;
  use Psr\Log\NullLogger;
  use Symfony\Component\Console\Command\Command;
  use Symfony\Component\Console\Input\ArrayInput;
  use Symfony\Component\Console\Tester\CommandTester;

  /**
   * Tests for the Drush commands.
   */
  #[Group('migrate_tools')]
  #[RunTestsInSeparateProcesses]
  final class DrushTest extends MigrateTestBase {

    /**
     * {@inheritdoc}
     */
    protected static $modules = [
      'migrate_tools_test',
      'migrate_tools',
      'migrate_plus',
      'taxonomy',
      'filter',
      'text',
      'system',
      'user',
    ];

    /**
     * The Migration plugin manager.
     */
    private MigrationPluginManagerInterface $migrationPluginManager;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void {
      parent::setUp();
      $this->installConfig('migrate_plus');
      $this->installConfig('migrate_tools_test');
      $this->installEntitySchema('taxonomy_term');
      $this->installEntitySchema('user');
      $this->installSchema('user', ['users_data']);
      $this->installSchema('migrate_tools', ['migrate_tools_sync_source_ids']);
      $this->migrationPluginManager = $this->container->get('plugin.manager.migration');
    }

    /**
     * Creates a command the way Drush does.
     *
     * @param class-string<\Symfony\Component\Console\Command\Command> $class
     *   The command class.
     *
     * @return \Symfony\Component\Console\Command\Command
     *   The command.
     */
    private function command(string $class): Command {
      // Services the Drush container provides.
      $this->container->set(LoggerInterface::class, new NullLogger());
      $this->container->set(FormatterManager::class, new FormatterManager());
      return $class::create($this->container);
    }

    /**
     * Runs a command that writes no structured output.
     *
     * @param class-string<\Symfony\Component\Console\Command\Command> $class
     *   The command class.
     * @param array $input
     *   The command arguments and options.
     */
    private function runCommand(string $class, array $input): void {
      (new CommandTester($this->command($class)))->execute($input);
    }

    /**
     * Returns the rows a formatted command would print.
     *
     * @param class-string<\Symfony\Component\Console\Command\Command> $class
     *   The command class, which has a doExecute() method.
     * @param array $input
     *   The command arguments and options.
     *
     * @return array
     *   The rows.
     */
    private function rows(string $class, array $input = []): array {
      $command = $this->command($class);
      $result = $command->doExecute(new ArrayInput($input, $command->getDefinition()));
      return $result instanceof RowsOfFields ? $result->getArrayCopy() : [];
    }

    /**
     * Tests drush ms.
     */
    public function testStatus(): void {
      $this->executeMigration('fruit_terms');
      $rows = $this->rows(MigrateStatusCommand::class, ['migration_names' => 'fruit_terms']);
      $this->assertCount(1, $rows);
      $row = reset($rows);
      $this->assertSame('fruit_terms', $row['id']);
      $this->assertSame(3, $row['total']);
      $this->assertSame(3, $row['imported']);
      $this->assertSame('Idle', $row['status']);

      // Migrations whose requirements are not met are not listed.
      $this->assertTrue($this->migrationPluginManager->hasDefinition('requirements_not_met'));
      $rows = $this->rows(MigrateStatusCommand::class, ['--continue-on-failure' => TRUE]);
      $this->assertNotContains('requirements_not_met', array_column($rows, 'id'));
    }

    /**
     * Tests that a failing status throws an exception (i.e. exit code).
     */
    public function testFailingStatusThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('The "does_not_exist" plugin does not exist.');
      $this->rows(MigrateStatusCommand::class, ['migration_names' => 'invalid_plugin']);
    }

    /**
     * Tests drush mim.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     */
    public function testImport(): void {
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance('fruit_terms');
      $id_map = $migration->getIdMap();
      $this->runCommand(MigrateImportCommand::class, ['migration_names' => 'fruit_terms', '--idlist' => 'Apple']);
      $this->assertSame(1, $id_map->importedCount());
      $this->runCommand(MigrateImportCommand::class, ['migration_names' => 'fruit_terms']);
      $this->assertSame(3, $id_map->importedCount());
      $this->runCommand(MigrateImportCommand::class, [
        'migration_names' => 'fruit_terms',
        '--idlist' => 'Apple',
        '--update' => TRUE,
      ]);
      $this->assertCount(0, $id_map->getRowsNeedingUpdate(100));
    }

    /**
     * Tests that a failing import throws an exception (i.e. exit code).
     */
    public function testFailingImportThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('source_exception migration failed.');
      $this->runCommand(MigrateImportCommand::class, ['migration_names' => 'source_exception']);
    }

    /**
     * Tests drush mmsg.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     */
    public function testMessages(): void {
      $this->executeMigration('fruit_terms');
      $message = $this->getRandomGenerator()->string(16);
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance('fruit_terms');
      $id_map = $migration->getIdMap();
      $id_map->saveMessage(['name' => 'Apple'], $message);
      $rows = $this->rows(MigrateMessagesCommand::class, ['migration_id' => 'fruit_terms']);
      $this->assertSame($message, $rows[0]['message']);
    }

    /**
     * Tests that a failing messages throws an exception (i.e. exit code).
     */
    public function testFailingMessagesThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('Migration does_not_exist does not exist');
      $this->rows(MigrateMessagesCommand::class, ['migration_id' => 'does_not_exist']);
    }

    /**
     * Tests drush mr.
     */
    public function testRollback(): void {
      $this->executeMigration('fruit_terms');
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance('fruit_terms');
      $id_map = $migration->getIdMap();
      $this->assertSame(3, $id_map->importedCount());
      $this->runCommand(MigrateRollbackCommand::class, ['migration_names' => 'fruit_terms']);
      $this->assertSame(0, $id_map->importedCount());
    }

    /**
     * Tests that a failing rollback throws an exception (i.e. exit code).
     */
    public function testFailingRollbackThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('source_exception migration failed');
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance('source_exception');
      $migration->setStatus(MigrationInterface::STATUS_IMPORTING);
      $this->runCommand(MigrateRollbackCommand::class, ['migration_names' => 'source_exception']);
    }

    /**
     * Tests drush mrs.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     */
    public function testReset(): void {
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance('fruit_terms');
      $migration->setStatus(MigrationInterface::STATUS_IMPORTING);
      $this->assertSame('Importing', $this->rows(MigrateStatusCommand::class, ['migration_names' => 'fruit_terms'])[0]['status']);
      $this->runCommand(MigrateResetStatusCommand::class, ['migration_id' => 'fruit_terms']);
      $this->assertSame(MigrationInterface::STATUS_IDLE, $migration->getStatus());

    }

    /**
     * Tests that a failing reset status throws an exception (i.e. exit code).
     */
    public function testFailingResetStatusThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('Migration does_not_exist does not exist');
      $this->runCommand(MigrateResetStatusCommand::class, ['migration_id' => 'does_not_exist']);
    }

    /**
     * Tests drush mst.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     */
    public function testStop(): void {
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance('fruit_terms');
      $migration->setStatus(MigrationInterface::STATUS_IMPORTING);
      $this->runCommand(MigrateStopCommand::class, ['migration_id' => 'fruit_terms']);
      $this->assertSame(MigrationInterface::STATUS_STOPPING, $migration->getStatus());
    }

    /**
     * Tests that a failing stop throws an exception (i.e. exit code).
     */
    public function testFailingStopThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('Migration does_not_exist does not exist');
      $this->runCommand(MigrateStopCommand::class, ['migration_id' => 'does_not_exist']);
    }

    /**
     * Tests drush mfs.
     */
    public function testFieldsSource(): void {
      $rows = $this->rows(MigrateFieldsSourceCommand::class, ['migration_id' => 'fruit_terms']);
      $this->assertCount(1, $rows);
      $this->assertSame('name', $rows[0]['machine_name']);
      $this->assertSame('name', $rows[0]['description']);
    }

    /**
     * Tests that a failing fields source throws an exception (i.e. exit code).
     */
    public function testFailingFieldsSourceThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('Migration does_not_exist does not exist');
      $this->rows(MigrateFieldsSourceCommand::class, ['migration_id' => 'does_not_exist']);
    }

    /**
     * Tests drush mim --uid switches user during import.
     */
    public function testImportWithUidOption(): void {
      // Record the original current user ID (should be anonymous).
      $original_uid = (int) \Drupal::currentUser()->id();

      // Create a test user to switch to during import.
      $account = User::create([
        'name' => 'migrate_test_user',
        'mail' => 'migrate_test_user@example.com',
        'status' => 1,
      ]);
      $account->save();
      $target_uid = (int) $account->id();
      $this->assertGreaterThan(0, $target_uid);

      // Register a PRE_ROW_SAVE event listener to capture the current user
      // ID during migration execution, proving the account was switched.
      $captured_uid = NULL;
      $listener = static function () use (&$captured_uid): void {
        $captured_uid = (int) \Drupal::currentUser()->id();
      };
      /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
      $dispatcher = \Drupal::service('event_dispatcher');
      $dispatcher->addListener(MigrateEvents::PRE_ROW_SAVE, $listener);

      // Run the import with the --uid option.
      $this->runCommand(MigrateImportCommand::class, ['migration_names' => 'fruit_terms', '--uid' => $target_uid]);

      // Verify the user was switched to the target user during import.
      $this->assertSame($target_uid, $captured_uid);

      // Verify the user was switched back to the original after import.
      $this->assertSame($original_uid, (int) \Drupal::currentUser()->id());

      // Clean up the event listener.
      $dispatcher->removeListener(MigrateEvents::PRE_ROW_SAVE, $listener);
    }

    /**
     * Tests drush mim without --uid does not switch user.
     */
    public function testImportWithoutUidOption(): void {
      $original_uid = (int) \Drupal::currentUser()->id();

      // Register a PRE_ROW_SAVE event listener to capture the current user
      // ID during migration execution.
      $captured_uid = NULL;
      $listener = static function () use (&$captured_uid): void {
        $captured_uid = (int) \Drupal::currentUser()->id();
      };
      /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
      $dispatcher = \Drupal::service('event_dispatcher');
      $dispatcher->addListener(MigrateEvents::PRE_ROW_SAVE, $listener);

      // Run the import without the --uid option.
      $this->runCommand(MigrateImportCommand::class, ['migration_names' => 'fruit_terms']);

      // Verify the user remains the original (anonymous) during import.
      $this->assertSame($original_uid, $captured_uid);

      // Verify the user is still the original after import.
      $this->assertSame($original_uid, (int) \Drupal::currentUser()->id());

      // Clean up the event listener.
      $dispatcher->removeListener(MigrateEvents::PRE_ROW_SAVE, $listener);
    }

    /**
     * Tests that import with a non-existent uid throws an exception.
     */
    public function testImportWithInvalidUidThrowsException(): void {
      $this->expectException(\Exception::class);
      $this->expectExceptionMessage('Unable to load user by uid: 9999');
      $this->runCommand(MigrateImportCommand::class, ['migration_names' => 'fruit_terms', '--uid' => 9999]);
    }

    /**
     * Tests that import with a blocked user throws an exception.
     */
    public function testImportWithBlockedUserThrowsException(): void {
      $account = User::create([
        'name' => 'blocked_user',
        'mail' => 'blocked@example.com',
        'status' => 0,
      ]);
      $account->save();

      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage('Account blocked_user is blocked');
      $this->runCommand(MigrateImportCommand::class, [
        'migration_names' => 'fruit_terms',
        '--uid' => (int) $account->id(),
      ]);
    }

    /**
     * Tests that optional dependencies are respected in migration ordering.
     */
    public function testOptionalDependencyOrdering(): void {
      $rows = $this->rows(MigrateStatusCommand::class, [
        'migration_names' => 'order_beta,order_gamma,order_alpha',
        '--names-only' => TRUE,
      ]);
      $this->assertSame('order_gamma', $rows[0]['id']);
      $this->assertSame('order_beta', $rows[1]['id']);
      $this->assertSame('order_alpha', $rows[2]['id']);
    }

  }
}

namespace {
  if (!function_exists('dt')) {

    /**
     * Stub for dt().
     *
     * @param string $message
     *   The text.
     * @param array $replace
     *   The replacement values.
     *
     *   The text.
     */
    function dt($message, array $replace = []): string {
      return strtr($message, $replace);
    }

  }

  if (!function_exists('drush_op')) {

    /**
     * Stub for drush_op.
     *
     * @param callable $callable
     *   The function to call.
     */
    function drush_op(callable $callable) {
      $args = func_get_args();
      array_shift($args);
      return call_user_func_array($callable, $args);
    }

  }

}
