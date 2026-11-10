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
 * Disables a migration.
 */
#[AsCommand(
  name: 'migrate:disable',
  description: 'Disable a migration.',
  aliases: ['mdis', 'migrate-disable'],
)]
final class MigrateDisableCommand extends Command {

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
    $this->addArgument('migration_id', InputArgument::REQUIRED, 'The ID of migration to disable.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $migration_id = $input->getArgument('migration_id');
    $migration = $this->loadMigration($migration_id);
    if ($migration->getStatus() == MigrationInterface::STATUS_DISABLED) {
      $this->logger->warning(\dt('Migration @id is already disabled', ['@id' => $migration_id]));
    }
    else {
      $migration->setStatus(MigrationInterface::STATUS_DISABLED);
      $this->logger->notice(\dt('Migration @id disabled', ['@id' => $migration_id]));
    }
    return self::SUCCESS;
  }

}
