<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drupal\migrate_tools\DrushLogMigrateMessage;

/**
 * Shared helpers for the Migrate Tools Drush commands.
 *
 * Expects $migrationPluginManager and $logger properties.
 */
trait MigrateToolsCommandTrait {

  /**
   * Migrate message logger.
   */
  protected ?DrushLogMigrateMessage $migrateMessage = NULL;

  /**
   * Retrieve a list of active migrations.
   *
   * @param string|null $migration_ids
   *   Comma-separated list of migrations -
   *   if present, return only these migrations.
   * @param array $options
   *   Command options.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface[][]
   *   An array keyed by migration group, each value containing an array of
   *   migrations or an empty array if no migrations match the input criteria.
   */
  protected function migrationsList(?string $migration_ids, array $options): array {
    $filter = [];
    // Filter keys must match the migration configuration property name.
    $filter['migration_group'] = $filter['migration_tags'] = [];
    if (!empty($options['group'])) {
      $filter['migration_group'] = explode(',', (string) $options['group']);
    }
    if (!empty($options['tag'])) {
      $filter['migration_tags'] = explode(',', (string) $options['tag']);
    }

    $manager = $this->migrationPluginManager;

    $matched_migration_definitions = $manager->getDefinitions();

    // Filter by specific migration IDs.
    if ($migration_ids) {
      $filtered_migrations = [];

      // Get the requested migrations.
      $migration_ids = explode(',', mb_strtolower($migration_ids));

      foreach ($migration_ids as $given_migration_id) {
        if (isset($matched_migration_definitions[$given_migration_id])) {
          $filtered_migrations[$given_migration_id] = $matched_migration_definitions[$given_migration_id];
        }
        else {
          $error_message = \dt('Migration @id does not exist', ['@id' => $given_migration_id]);
          if (!empty($options['continue-on-failure'])) {
            $this->logger->error($error_message);
          }
          else {
            throw new \Exception($error_message);
          }
        }
      }

      $matched_migration_definitions = $filtered_migrations;
    }

    // Filters the matched migrations if a group or a tag has been input.
    if (!empty($filter['migration_group']) || !empty($filter['migration_tags'])) {
      // Get migrations in any of the specified groups and with any of the
      // specified tags.
      foreach ($filter as $property => $values) {
        if (!empty($values)) {
          $filtered_migrations = [];
          foreach ($values as $search_value) {
            foreach ($matched_migration_definitions as $id => $migration_definition) {
              // Cast to array because migration_tags can be an array.
              $configured_values = (array) ($migration_definition[$property] ?? NULL);
              $configured_id = in_array($search_value, $configured_values, TRUE) ? $search_value : 'default';
              if (empty($search_value) || $search_value === $configured_id) {
                if (empty($migration_ids) || in_array(mb_strtolower($id), $migration_ids, TRUE)) {
                  $filtered_migrations[$id] = $migration_definition;
                }
              }
            }
          }
          $matched_migration_definitions = $filtered_migrations;
        }
      }
    }

    $matched_migrations = [];
    foreach (array_keys($matched_migration_definitions) as $id) {
      $matched_migrations[$id] = $manager->createInstance($id);
    }

    // Do not return any migrations which fail to meet requirements.
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    foreach ($matched_migrations as $id => $migration) {
      try {
        if ($migration->getSourcePlugin() instanceof RequirementsInterface) {
          $migration->getSourcePlugin()->checkRequirements();
        }
      }
      catch (RequirementsException) {
        unset($matched_migrations[$id]);
      }
      catch (PluginNotFoundException $exception) {
        if (!empty($options['continue-on-failure'])) {
          $this->logger->error($exception->getMessage());
          unset($matched_migrations[$id]);
        }
        else {
          throw $exception;
        }
      }
    }

    foreach ($matched_migrations as $migration) {
      $migration->set('migration_dependencies', $migration->getMigrationDependencies());
    }
    $matched_migrations = $manager->buildDependencyMigration($matched_migrations, []);

    // Sort the matched migrations by group.
    $migrations = [];
    foreach ($matched_migrations as $id => $migration) {
      $configured_group_id = empty($migration->migration_group) ? 'default' : $migration->migration_group;
      $migrations[$configured_group_id][$id] = $migration;
    }
    return $migrations;
  }

  /**
   * Gets the migrate message logger.
   */
  protected function getMigrateMessage(): DrushLogMigrateMessage {
    if (!isset($this->migrateMessage)) {
      $this->migrateMessage = new DrushLogMigrateMessage($this->logger);
    }
    return $this->migrateMessage;
  }

  /**
   * Loads a migration, or throws if it does not exist.
   *
   * @param string $migration_id
   *   The migration ID.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration.
   */
  protected function loadMigration(string $migration_id): MigrationInterface {
    $migration = $this->migrationPluginManager->createInstance($migration_id);
    if (!$migration) {
      $error = \dt('Migration @id does not exist', ['@id' => $migration_id]);
      $this->logger->error($error);
      throw new \Exception($error);
    }
    return $migration;
  }

}
