<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Drush\Commands;

use Drupal\entity_usage\EntityUsageBatchManager;
use Drush\Attributes\Bootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Entity Usage drush commands.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Recreate all entity usage statistics.',
  aliases: ['eu-r', 'entity-usage-recreate'],
)]
#[Bootstrap(DrupalBootLevels::FULL)]
class RecreateEntityUsageCommand extends Command {
  use AutowireTrait;

  public const string NAME = 'entity-usage:recreate';

  /**
   * {@inheritdoc}
   */
  public function __construct(protected EntityUsageBatchManager $batchManager) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addUsage(self::NAME)
      ->addOption('keep-existing-records', description: 'When --keep-existing-records is used, existing entity usage records won\'t be deleted.')
      ->addOption('entity-types', mode: InputOption::VALUE_OPTIONAL, description: 'A comma-separated list of entity type IDs to recreate usage statistics for, e.g. "node,media". If omitted, statistics are recreated for all entity types enabled for tracking. When provided, only usage records for these entity types are deleted and rebuilt.');
  }

  /**
   * Recreate all entity usage statistics.
   */
  public function execute(InputInterface $input, OutputInterface $output): int {
    $entity_types = NULL;
    $entity_types_option = $input->getOption('entity-types');
    if (!empty($entity_types_option)) {
      $entity_types = array_map('trim', explode(',', $entity_types_option));
    }
    $this->batchManager->recreate((bool) $input->getOption('keep-existing-records'), $entity_types);
    drush_backend_batch_process();
    return Command::SUCCESS;
  }

}
