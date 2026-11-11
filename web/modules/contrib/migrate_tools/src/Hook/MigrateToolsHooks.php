<?php

namespace Drupal\migrate_tools\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Row;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate_tools\Form\MigrationGroupDeleteForm;
use Drupal\migrate_tools\Form\MigrationGroupEditForm;
use Drupal\migrate_tools\Form\MigrationGroupAddForm;
use Drupal\migrate_tools\Controller\MigrationGroupListBuilder;
use Drupal\migrate_tools\Form\MigrationDeleteForm;
use Drupal\migrate_tools\Form\MigrationEditForm;
use Drupal\migrate_tools\Controller\MigrationListBuilder;
use Drupal\migrate_tools\MigrateIncludeHandler;
use Drupal\migrate_tools\MigrateTools;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for migrate_tools.
 */
class MigrateToolsHooks {

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly MigrateIncludeHandler $includeHandler,
    protected readonly MigrateTools $migrateTools,
  ) {}

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    // Inject our UI into the general migration and migration group config
    // entities.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityType[] $entity_types */
    if (array_key_exists('migration', $entity_types)) {
      $entity_types['migration']->set('admin_permission', 'administer migrations')->setHandlerClass('list_builder', MigrationListBuilder::class)->setFormClass('edit', MigrationEditForm::class)->setFormClass('delete', MigrationDeleteForm::class)->setLinkTemplate('list-form', '/admin/structure/migrate/manage/{migration_group}/migrations');
    }
    if (array_key_exists('migration_group', $entity_types)) {
      $entity_types['migration_group']->set('admin_permission', 'administer migrations')->setHandlerClass('list_builder', MigrationGroupListBuilder::class)->setFormClass('add', MigrationGroupAddForm::class)->setFormClass('edit', MigrationGroupEditForm::class)->setFormClass('delete', MigrationGroupDeleteForm::class)->setLinkTemplate('edit-form', '/admin/structure/migrate/manage/{migration_group}')->setLinkTemplate('delete-form', '/admin/structure/migrate/manage/{migration_group}/delete');
    }
  }

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(&$links): void {
    if ($this->moduleHandler->moduleExists('migrate_plus')) {
      $links['migrate_tools.menu'] = [
        'title' => 'Migrations',
        'route_name' => 'entity.migration_group.list',
        'description' => new TranslatableMarkup('Manage migration processes.'),
        'parent' => 'system.admin_structure',
      ];
    }
  }

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$migrations): void {
    foreach (array_keys($migrations) as $id) {
      // Process all includes.
      if (!array_key_exists('include', $migrations[$id])) {
        continue;
      }
      $this->includeHandler->include($migrations[$id]);
    }
  }

  /**
   * Implements hook_migrate_prepare_row().
   *
   * @throws \Exception
   *
   * @see \Drupal\migrate_tools\EventSubscriber\MigrationImportSync
   * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase::next()
   */
  #[Hook('migrate_prepare_row')]
  public function migratePrepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration): void {
    // Act on migrations that have a Sync source, and that are currently in the
    // phase of Syncing their IDs.
    if (!empty($migration->syncSource) && $this->migrateTools->isMigrationSyncing($migration->getPluginId())) {
      // Keep track of all source rows here, as SourcePluginBase::next() might
      // skip some rows, and we need them all to detect missing items in source
      // to delete in destination.
      $this->migrateTools->addToSyncSourceIds($migration, $row->getSourceIdValues());
    }
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityType $entity_type */
    if (isset($entity_types['migration']) && $entity_type = $entity_types['migration']) {
      $entity_type->set('config_export', array_merge($entity_type->get('config_export'), [
        'include',
      ]));
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    if (isset($definitions['migrate_plus.migration.*'])) {
      $definitions['migrate_plus.migration.*']['mapping']['include'] = [
        'type' => 'sequence',
        'label' => 'Included migration configuration',
        'nullable' => TRUE,
        'sequences' => [
          'type' => 'string',
          'label' => 'Included shared configuration entry',
        ],
      ];
    }
  }

}
