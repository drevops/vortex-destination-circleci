<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\Drush\MigrateToolsCommandTrait;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stops an active migration operation.
 */
#[AsCommand(
  name: 'migrate:stop',
  description: 'Stop an active migration operation.',
  aliases: ['mst', 'migrate-stop'],
)]
final class MigrateStopCommand extends Command {

  use AutowireTrait;
  use MigrateToolsCommandTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.migration')]
    protected readonly MigrationPluginManager $migrationPluginManager,
    protected readonly LoggerInterface $logger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->addArgument('migration_id', InputArgument::REQUIRED, 'The ID of migration to stop.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $migration_id = $input->getArgument('migration_id');
    $migration = $this->loadMigration($migration_id);
    switch ($migration->getStatus()) {
      case MigrationInterface::STATUS_IDLE:
        $this->logger->warning(\dt('Migration @id is idle', ['@id' => $migration_id]));
        break;

      case MigrationInterface::STATUS_DISABLED:
        $this->logger->warning(\dt('Migration @id is disabled', ['@id' => $migration_id]));
        break;

      case MigrationInterface::STATUS_STOPPING:
        $this->logger->warning(\dt('Migration @id is already stopping', ['@id' => $migration_id]));
        break;

      default:
        $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
        $this->logger->notice(\dt('Migration @id requested to stop', ['@id' => $migration_id]));
        break;
    }
    return self::SUCCESS;
  }

}
