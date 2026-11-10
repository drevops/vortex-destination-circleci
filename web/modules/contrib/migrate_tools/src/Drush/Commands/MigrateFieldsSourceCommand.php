<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\Drush\MigrateToolsCommandTrait;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lists the fields available for mapping in a source.
 */
#[AsCommand(
  name: 'migrate:fields-source',
  description: 'List the fields available for mapping in a source.',
  aliases: ['mfs', 'migrate-fields-source'],
)]
#[CLI\FieldLabels(labels: [
  'machine_name' => 'Machine Name',
  'description' => 'Description',
])]
#[CLI\DefaultFields(fields: ['machine_name', 'description'])]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class MigrateFieldsSourceCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;
  use MigrateToolsCommandTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.migration')]
    protected readonly MigrationPluginManager $migrationPluginManager,
    protected readonly LoggerInterface $logger,
    protected readonly FormatterManager $formatterManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('migration_id', InputArgument::REQUIRED, 'The ID of the migration.')
      ->addUsage('migrate:fields-source my_node');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->writeFormattedOutput($input, $output, $this->doExecute($input));
    return self::SUCCESS;
  }

  /**
   * Builds the source field rows.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The command input.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The source fields.
   */
  public function doExecute(InputInterface $input): RowsOfFields {
    $migration = $this->loadMigration($input->getArgument('migration_id'));
    $table = [];
    foreach ($migration->getSourcePlugin()->fields() as $machine_name => $description) {
      $table[] = [
        'machine_name' => $machine_name,
        'description' => strip_tags((string) $description),
      ];
    }
    return new RowsOfFields($table);
  }

}
