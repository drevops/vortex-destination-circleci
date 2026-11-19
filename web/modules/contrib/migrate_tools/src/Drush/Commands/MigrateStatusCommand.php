<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lists all migrations with their current status.
 */
#[AsCommand(
  name: 'migrate:status',
  description: 'List all migrations with current status.',
  aliases: ['ms', 'migrate-status'],
)]
#[CLI\FieldLabels(labels: [
  'group' => 'Group',
  'id' => 'Migration ID',
  'status' => 'Status',
  'total' => 'Total',
  'imported' => 'Imported',
  'unprocessed' => 'Unprocessed',
  'message_count' => 'Message Count',
  'last_imported' => 'Last Imported',
])]
#[CLI\DefaultFields(fields: [
  'group',
  'id',
  'status',
  'total',
  'imported',
  'unprocessed',
  'message_count',
  'last_imported',
])]
#[CLI\FilterDefaultField(field: 'status')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class MigrateStatusCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;
  use MigrateToolsCommandTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.migration')]
    protected readonly MigrationPluginManager $migrationPluginManager,
    #[Autowire(service: 'entity_type.manager')]
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'keyvalue')]
    protected readonly KeyValueFactoryInterface $keyValue,
    #[Autowire(service: 'date.formatter')]
    protected readonly DateFormatterInterface $dateFormatter,
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
      ->addArgument('migration_names', InputArgument::OPTIONAL, 'Restrict to a comma-separated list of migrations. Optional.')
      ->addOption('group', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration groups to list')
      ->addOption('tag', NULL, InputOption::VALUE_REQUIRED, 'Name of the migration tag to list')
      ->addOption('names-only', NULL, InputOption::VALUE_NONE, 'Only return names, not all the details (faster)')
      ->addOption('continue-on-failure', NULL, InputOption::VALUE_NONE, 'When a migration fails, continue processing remaining migrations.')
      ->addUsage('migrate:status')
      ->addUsage('migrate:status --group=beer')
      ->addUsage('migrate:status --tag=user')
      ->addUsage('migrate:status beer_term,beer_node')
      ->addUsage('migrate:status --field=id')
      ->addUsage('ms --fields=id,status --format=json');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->writeFormattedOutput($input, $output, $this->doExecute($input));
    return self::SUCCESS;
  }

  /**
   * Builds the status rows.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The command input.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The migration status rows.
   */
  public function doExecute(InputInterface $input): RowsOfFields {
    $options = $input->getOptions();
    $names_only = $options['names-only'];

    $migrations = $this->migrationsList($input->getArgument('migration_names'), $options);

    $table = [];
    $errors = [];
    // Take it one group at a time, listing the migrations within each group.
    $group_entity_exists = $this->entityTypeManager->hasHandler('migration_group', 'storage');
    $group_name = 'Default (default)';
    foreach ($migrations as $group_id => $migration_list) {
      if ($group_entity_exists) {
        /** @var \Drupal\migrate_plus\Entity\MigrationGroup $group */
        $group = $this->entityTypeManager->getStorage('migration_group')->load($group_id);
        $group_name = !empty($group) ? "{$group->label()} ({$group->id()})" : $group_id;
      }

      foreach ($migration_list as $migration_id => $migration) {
        if ($names_only) {
          $table[] = [
            'group' => \dt('Group: @name', ['@name' => $group_name]),
            'id' => $migration_id,
          ];
          continue;
        }

        try {
          $map = $migration->getIdMap();
          $imported = $map->importedCount();
          $source_plugin = $migration->getSourcePlugin();
        }
        catch (\Exception $e) {
          $error = \dt('Failure retrieving information on @migration: @message', [
            '@migration' => $migration_id,
            '@message' => $e->getMessage(),
          ]);
          $this->logger->error($error);
          $errors[] = $error;
          continue;
        }

        try {
          $source_rows = $source_plugin->count();
          // -1 indicates uncountable sources.
          if ($source_rows == -1) {
            $source_rows = \dt('N/A');
            $unprocessed = \dt('N/A');
          }
          else {
            $unprocessed = $source_rows - $map->processedCount();
          }
        }
        catch (\Exception $e) {
          $this->logger->error(\dt('Could not retrieve source count from @migration: @message', [
            '@migration' => $migration_id,
            '@message' => $e->getMessage(),
          ]));
          $source_rows = \dt('N/A');
          $unprocessed = \dt('N/A');
        }

        $last_imported = $this->keyValue->get('migrate_last_imported')->get($migration->id(), FALSE);
        $table[] = [
          'group' => $group_name,
          'id' => $migration_id,
          'status' => $migration->getStatusLabel(),
          'total' => $source_rows,
          'imported' => $imported,
          'unprocessed' => $unprocessed,
          'last_imported' => $last_imported ? $this->dateFormatter->format((int) ($last_imported / 1000), 'custom', 'Y-m-d H:i:s') : '',
          'message_count' => $map->messageCount(),
        ];
      }
      if ($group_id !== array_key_last($migrations)) {
        $table[] = [];
      }
    }

    // If any errors occurred, throw an exception.
    if (!empty($errors)) {
      throw new \Exception(implode(PHP_EOL, $errors));
    }

    return new RowsOfFields($table);
  }

}
