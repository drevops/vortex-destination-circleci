<?php

declare(strict_types=1);

namespace Drupal\migrate_tools_test\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs a batch import of fruit terms as a test.
 */
#[AsCommand(name: 'migrate:batch-import-fruit', description: 'Run a batch import of fruit terms as a test.')]
final class MigrateBatchImportFruitCommand extends Command {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.migration')]
    private readonly MigrationPluginManager $migrationPluginManager,
    #[Autowire(service: 'keyvalue')]
    private readonly KeyValueFactoryInterface $keyValue,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $executable = new MigrateBatchExecutable(
      $this->migrationPluginManager->createInstance('fruit_terms'),
      new MigrateMessage(),
      $this->keyValue,
      $this->time,
      $this->translation,
      $this->migrationPluginManager,
    );
    $executable->batchImport();
    \drush_backend_batch_process();
    return self::SUCCESS;
  }

}
