<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\Drush\MigrateToolsCommandTrait;
use Drupal\migrate_tools\EventSubscriber\MigrationDrushCommandProgress;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\migrate_tools\MigrateTools;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Performs one or more migration processes.
 */
#[AsCommand(
  name: 'migrate:import',
  description: 'Perform one or more migration processes.',
  aliases: ['mim', 'migrate-import'],
)]
final class MigrateImportCommand extends Command {

  use AutowireTrait;
  use MigrateToolsCommandTrait;

  /**
   * Migrations run during this command, so none runs twice.
   *
   * @var string[]
   */
  private array $executedMigrations = [];

  public function __construct(
    #[Autowire(service: 'plugin.manager.migration')]
    protected readonly MigrationPluginManager $migrationPluginManager,
    #[Autowire(service: 'entity_type.manager')]
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'keyvalue')]
    protected readonly KeyValueFactoryInterface $keyValue,
    #[Autowire(service: 'datetime.time')]
    protected readonly TimeInterface $time,
    #[Autowire(service: 'string_translation')]
    protected readonly TranslationInterface $translation,
    #[Autowire(service: 'migrate_tools.migration_drush_command_progress')]
    protected readonly MigrationDrushCommandProgress $commandProgress,
    #[Autowire(service: 'account_switcher')]
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly LoggerInterface $logger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('migration_names', InputArgument::OPTIONAL, 'ID of migration(s) to import. Delimit multiple using commas.')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Process all migrations')
      ->addOption('group', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration groups to import')
      ->addOption('tag', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration tags to import')
      ->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Limit on the number of items to process in each migration')
      ->addOption('batch-size', NULL, InputOption::VALUE_REQUIRED, 'Optionally use batch iterations, with a limit on the number of items to process in each batch iteration.')
      ->addOption('feedback', NULL, InputOption::VALUE_REQUIRED, 'Frequency of progress messages, in items processed')
      ->addOption('idlist', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated list of IDs to import.')
      ->addOption('idlist-delimiter', NULL, InputOption::VALUE_REQUIRED, 'The delimiter for records', MigrateTools::DEFAULT_ID_LIST_DELIMITER)
      ->addOption('update', NULL, InputOption::VALUE_NONE, 'In addition to processing unprocessed items from the source, update previously-imported items with the current data')
      ->addOption('force', NULL, InputOption::VALUE_NONE, 'Force an operation to run, even if all dependencies are not satisfied')
      ->addOption('continue-on-failure', NULL, InputOption::VALUE_NONE, 'When a migration fails, continue processing remaining migrations.')
      ->addOption('execute-dependencies', NULL, InputOption::VALUE_NONE, 'Execute all dependent migrations first')
      ->addOption('skip-progress-bar', NULL, InputOption::VALUE_NONE, 'Skip displaying a progress bar.')
      ->addOption('sync', NULL, InputOption::VALUE_NONE, 'Sync source and destination. Delete destination records that do not exist in the source.')
      ->addOption('uid', NULL, InputOption::VALUE_REQUIRED, 'Run the import as the user with this ID.')
      ->addUsage('migrate:import --all')
      ->addUsage('migrate:import --group=beer')
      ->addUsage('migrate:import --group=beer --uid=1')
      ->addUsage('migrate:import --tag=user')
      ->addUsage('migrate:import --group=beer --tag=user')
      ->addUsage('migrate:import beer_term,beer_node')
      ->addUsage('migrate:import beer_user --limit=2')
      ->addUsage('migrate:import beer_user --idlist=5')
      ->addUsage('migrate:import beer_node_revision --idlist=1:2,2:3,3:5')
      ->addUsage('migrate:import beer_user --limit=50 --feedback=20')
      ->addUsage('migrate:import --all --sync');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $migration_names = $input->getArgument('migration_names');
    $options = $input->getOptions();
    if (!$options['all'] && !$options['group'] && !$migration_names && !$options['tag']) {
      throw new \Exception(\dt('You must specify --all, --group, --tag or one or more migration names separated by commas'));
    }

    $migrations = $this->migrationsList($migration_names, $options);
    if (empty($migrations)) {
      $this->logger->error(\dt('No migrations found.'));
    }

    $user_switched = FALSE;
    if ($options['uid'] !== NULL) {
      /** @var \Drupal\user\UserInterface|null $user */
      $user = $this->entityTypeManager->getStorage('user')->load($options['uid']);
      if (!$user) {
        throw new \Exception(\dt('Unable to load user by uid: @uid', ['@uid' => $options['uid']]));
      }
      if ($user->isBlocked()) {
        throw new \InvalidArgumentException(\dt('Account @name is blocked.', ['@name' => $user->getAccountName()]));
      }
      $this->accountSwitcher->switchTo($user);
      $user_switched = TRUE;
    }

    try {
      // Take it one group at a time, importing the migrations within each
      // group.
      foreach ($migrations as $migration_list) {
        foreach ($migration_list as $migration_id => $migration) {
          // Don't execute disabled migrations.
          if ($migration->getStatus() == MigrationInterface::STATUS_DISABLED) {
            continue;
          }
          $this->executeMigration($migration, $migration_id, $options, $output);
        }
      }
    }
    finally {
      if ($user_switched) {
        $this->accountSwitcher->switchBack();
      }
    }
    return self::SUCCESS;
  }

  /**
   * Executes a single migration.
   *
   * If the --execute-dependencies option was given, the migration's
   * dependencies are executed first.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration to execute.
   * @param string $migration_id
   *   The migration ID.
   * @param array $options
   *   The command options.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   *
   * @throws \Exception
   *   If some migrations failed during execution.
   */
  private function executeMigration(MigrationInterface $migration, string $migration_id, array $options, OutputInterface $output): void {
    if ($options['execute-dependencies']) {
      $required_migrations = array_filter($migration->getRequirements(), fn($value): bool => !isset($this->executedMigrations[$value]));
      if (!empty($required_migrations)) {
        $dependency_options = array_merge($options, ['is_dependency' => TRUE]);
        foreach ($this->migrationPluginManager->createInstances($required_migrations) as $required_id => $required_migration) {
          $this->executeMigration($required_migration, $required_id, $dependency_options, $output);
        }
      }
    }
    if ($options['sync']) {
      $migration->set('syncSource', TRUE);
    }
    if ($options['skip-progress-bar']) {
      $migration->set('skipProgressBar', TRUE);
    }
    if ($options['continue-on-failure']) {
      $migration->set('continueOnFailure', TRUE);
    }
    if ($options['force']) {
      $migration->set('requirements', []);
    }
    if ($options['update']) {
      if (!$options['idlist']) {
        $migration->getIdMap()->prepareUpdate();
      }
      else {
        $keys = array_keys($migration->getSourcePlugin()->getIds());
        foreach (MigrateTools::buildIdList($options) as $source_id_values) {
          $migration->getIdMap()->setUpdate(array_combine($keys, $source_id_values));
        }
      }
    }

    // Initialize the Symfony Console progress bar.
    $this->commandProgress->initializeProgress($output, $migration, $options);

    if (empty($options['batch-size'])) {
      $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $this->keyValue, $this->time, $this->translation, $options);
      // drush_op() provides --simulate support.
      $result = \drush_op([$executable, 'import']);
    }
    else {
      // MigrateBatchExecutable requires the `update` and `force` options to
      // strictly be integers.
      $options['update'] = (int) $options['update'];
      $options['force'] = (int) $options['force'];
      $executable = new MigrateBatchExecutable($migration, $this->getMigrateMessage(), $this->keyValue, $this->time, $this->translation, $this->migrationPluginManager, $options);
      $executable->batchImport();
      // drush_op() provides --simulate support.
      $result = \drush_op('drush_backend_batch_process');
    }

    $this->executedMigrations[$migration_id] = $migration_id;
    if ($count = $executable->getFailedCount()) {
      $error_message = \dt('@name Migration - @count failed.', ['@name' => $migration_id, '@count' => $count]);
    }
    elseif ($result == MigrationInterface::RESULT_FAILED) {
      $error_message = \dt('@name migration failed.', ['@name' => $migration_id]);
    }
    else {
      return;
    }
    if (!$options['continue-on-failure']) {
      // Nudge Drush to use a non-zero exit code.
      throw new \Exception($error_message);
    }
    $this->logger->error($error_message);
  }

}
