<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\Drush\MigrateToolsCommandTrait;
use Drupal\migrate_tools\EventSubscriber\MigrationDrushCommandProgress;
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
 * Rolls back one or more migrations.
 */
#[AsCommand(
  name: 'migrate:rollback',
  description: 'Rollback one or more migrations.',
  aliases: ['mr', 'migrate-rollback'],
)]
final class MigrateRollbackCommand extends Command {

  use AutowireTrait;
  use MigrateToolsCommandTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.migration')]
    protected readonly MigrationPluginManager $migrationPluginManager,
    #[Autowire(service: 'keyvalue')]
    protected readonly KeyValueFactoryInterface $keyValue,
    #[Autowire(service: 'datetime.time')]
    protected readonly TimeInterface $time,
    #[Autowire(service: 'string_translation')]
    protected readonly TranslationInterface $translation,
    #[Autowire(service: 'migrate_tools.migration_drush_command_progress')]
    protected readonly MigrationDrushCommandProgress $commandProgress,
    protected readonly LoggerInterface $logger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('migration_names', InputArgument::OPTIONAL, 'Comma-separated list of migration IDs.')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Process all migrations')
      ->addOption('group', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration groups to rollback')
      ->addOption('tag', NULL, InputOption::VALUE_REQUIRED, 'ID of the migration tag to rollback')
      ->addOption('feedback', NULL, InputOption::VALUE_REQUIRED, 'Frequency of progress messages, in items processed')
      ->addOption('idlist', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated list of IDs to rollback')
      ->addOption('idlist-delimiter', NULL, InputOption::VALUE_REQUIRED, 'The delimiter for records', MigrateTools::DEFAULT_ID_LIST_DELIMITER)
      ->addOption('continue-on-failure', NULL, InputOption::VALUE_NONE, 'When a migration fails, continue processing remaining migrations.')
      ->addOption('skip-progress-bar', NULL, InputOption::VALUE_NONE, 'Skip displaying a progress bar.')
      ->addUsage('migrate:rollback --all')
      ->addUsage('migrate:rollback --group=beer')
      ->addUsage('migrate:rollback --tag=user')
      ->addUsage('migrate:rollback --group=beer --tag=user')
      ->addUsage('migrate:rollback beer_term,beer_node')
      ->addUsage('migrate:rollback beer_user --idlist=5');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $migration_names = $input->getArgument('migration_names');
    $options = $input->getOptions();
    if (!$options['all'] && !$options['group'] && !$migration_names && !$options['tag']) {
      throw new \Exception(\dt('You must specify --all, --group, --tag, or one or more migration names separated by commas'));
    }

    $migrations = $this->migrationsList($migration_names, $options);
    if (empty($migrations)) {
      $this->logger->error(\dt('No migrations found.'));
    }

    // Take it one group at a time, rolling back the migrations within each
    // group.
    $errored_migration_id = NULL;
    foreach ($migrations as $migration_list) {
      // Roll back in reverse order.
      foreach (array_reverse($migration_list) as $migration_id => $migration) {
        // Skip disabled migrations.
        if ($migration->getStatus() == MigrationInterface::STATUS_DISABLED) {
          continue;
        }
        if ($options['skip-progress-bar']) {
          $migration->set('skipProgressBar', TRUE);
        }
        // Initialize the Symfony Console progress bar.
        $this->commandProgress->initializeProgress($output, $migration);
        $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $this->keyValue, $this->time, $this->translation, $options);
        // drush_op() provides --simulate support.
        if (\drush_op([$executable, 'rollback']) == MigrationInterface::RESULT_FAILED) {
          $errored_migration_id = $migration_id;
        }
      }
    }

    // If any rollbacks failed, throw an exception to generate exit status.
    if ($errored_migration_id !== NULL) {
      $error_message = \dt('@name migration failed.', ['@name' => $errored_migration_id]);
      if (!$options['continue-on-failure']) {
        throw new \Exception($error_message);
      }
      $this->logger->error($error_message);
    }
    return self::SUCCESS;
  }

}
