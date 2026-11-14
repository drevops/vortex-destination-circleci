<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\Drush\MigrateToolsCommandTrait;
use Drupal\migrate_tools\IdMapFilter;
use Drupal\migrate_tools\MigrateTools;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Shows the messages associated with a migration.
 */
#[AsCommand(
  name: 'migrate:messages',
  description: 'View any messages associated with a migration.',
  aliases: ['mmsg', 'migrate-messages'],
)]
#[CLI\FieldLabels(labels: [
  'source_ids_hash' => 'Source IDs Hash',
  'source_ids' => 'Source ID(s)',
  'destination_ids' => 'Destination ID(s)',
  'level' => 'Level',
  'message' => 'Message',
])]
#[CLI\DefaultFields(fields: [
  'source_ids_hash',
  'source_ids',
  'destination_ids',
  'level',
  'message',
])]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class MigrateMessagesCommand extends Command {

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
      ->addOption('idlist', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated list of IDs to show messages for')
      ->addOption('idlist-delimiter', NULL, InputOption::VALUE_REQUIRED, 'The delimiter for records', MigrateTools::DEFAULT_ID_LIST_DELIMITER)
      ->addUsage('migrate:messages article')
      ->addUsage('migrate:messages node_revision --idlist=1:2,2:3,3:5')
      ->addUsage('migrate:messages custom_node_revision --idlist=1:"r:1",2:"r:3"');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->writeFormattedOutput($input, $output, $this->doExecute($input));
    return self::SUCCESS;
  }

  /**
   * Builds the message rows.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The command input.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|null
   *   The message rows, or NULL when there are none.
   */
  public function doExecute(InputInterface $input): ?RowsOfFields {
    $migration = $this->loadMigration($input->getArgument('migration_id'));
    /** @var \Drupal\migrate\Plugin\MigrateIdMapInterface|\Drupal\migrate_tools\IdMapFilter $map */
    $map = new IdMapFilter($migration->getIdMap(), MigrateTools::buildIdList($input->getOptions()));
    $map->rewind();
    $columns = $map->currentSource();
    if ($columns === NULL) {
      $this->logger->notice(\dt('Migration has not yet run'));
      return NULL;
    }
    $source_id_keys = array_map(static fn($id): string => 'src_' . $id, array_keys($columns));
    $source_id_keys = array_combine($source_id_keys, $source_id_keys);

    $table = [];
    $level_mapping = MigrateTools::getLogLevelLabelMapping();
    foreach ($map->getMessages() as $row) {
      unset($row->msgid);
      $array_row = (array) $row;
      // If the message includes useful IDs don't print the hash.
      if (count($source_id_keys) === count(array_intersect_key($source_id_keys, $array_row))) {
        unset($array_row['source_ids_hash']);
      }
      $source_ids = $destination_ids = [];
      foreach ($array_row as $name => $item) {
        if (str_starts_with($name, 'src_')) {
          $source_ids[$name] = $item;
        }
        if (str_starts_with($name, 'dest_')) {
          $destination_ids[$name] = $item;
        }
      }
      $array_row['level'] = $level_mapping[$array_row['level']];
      $array_row['source_ids'] = implode(MigrateTools::DEFAULT_ID_LIST_DELIMITER, $source_ids);
      $array_row['destination_ids'] = array_filter($destination_ids) ? implode(MigrateTools::DEFAULT_ID_LIST_DELIMITER, $destination_ids) : '';
      $table[] = $array_row;
    }
    if (empty($table)) {
      $this->logger->notice(\dt('No messages for this migration'));
      return NULL;
    }
    return new RowsOfFields($table);
  }

}
