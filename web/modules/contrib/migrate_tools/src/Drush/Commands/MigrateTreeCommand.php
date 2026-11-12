<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\Drush\MigrateToolsCommandTrait;
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
 * Shows a tree of migration dependencies.
 */
#[AsCommand(
  name: 'migrate:tree',
  description: 'Show a tree of migration dependencies.',
)]
final class MigrateTreeCommand extends Command {

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
    $this
      ->addArgument('migration_names', InputArgument::OPTIONAL, 'Restrict to a comma-separated list of migrations (Optional).')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Process all migrations')
      ->addOption('group', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration groups to import')
      ->addOption('tag', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration tags to import');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $migrations = $this->migrationsList($input->getArgument('migration_names'), $input->getOptions());

    // Turn this into a flat array.
    $migrations_to_process = [];
    foreach ($migrations as $group_migrations) {
      foreach ($group_migrations as $migration) {
        $migrations_to_process[$migration->id()] = $migration;
      }
    }

    // Create a dependency graph. The migrations in the given list may have
    // dependencies not in the list, so we need to add those to the list as we
    // go.
    $dependency_graph = [];
    while (!empty($migrations_to_process)) {
      $migration = reset($migrations_to_process);
      unset($migrations_to_process[$migration->id()]);
      $migration_dependencies = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.0.0', fn() => $migration->getMigrationDependencies(), fn() => $migration->getMigrationDependencies(TRUE));

      $dependency_graph[$migration->id()]['edges'] = [];
      foreach ($migration_dependencies['required'] ?? [] as $dependency) {
        $dependency_graph[$migration->id()]['edges'][$dependency] = $dependency;
        $migrations_to_process[$dependency] = $this->migrationPluginManager->createInstance($dependency);
      }
    }

    $dependency_graph = (new Graph($dependency_graph))->searchAndSort();

    // Print each top-level migration, that is, one nothing depends on.
    foreach ($dependency_graph as $migration_name => $vertex) {
      if (empty($vertex['reverse_paths'])) {
        $output->writeln($migration_name);
        $this->printDependencies($output, '', $dependency_graph, $migration_name);
      }
    }
    return self::SUCCESS;
  }

  /**
   * Prints the dependencies of a single migration in the dependency tree.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The command output.
   * @param string $prefix
   *   The prefix for the current level's lines.
   * @param array $dependency_graph
   *   The complete dependency graph.
   * @param string $migration_name
   *   The name of the migration to print dependencies for.
   */
  private function printDependencies(OutputInterface $output, string $prefix, array $dependency_graph, string $migration_name): void {
    $last_edge = end($dependency_graph[$migration_name]['edges']);
    foreach ($dependency_graph[$migration_name]['edges'] as $edge) {
      if ($edge === $last_edge) {
        $tree_string = '└──';
        $subtree_prefix = $prefix . '   ';
      }
      else {
        $tree_string = '├──';
        $subtree_prefix = $prefix . '│  ';
      }
      $output->writeln($prefix . $tree_string . $edge);
      $this->printDependencies($output, $subtree_prefix, $dependency_graph, $edge);
    }
  }

}
