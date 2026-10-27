<?php

declare(strict_types=1);

namespace Drupal\deploy_steps_example\Plugin\DeployStep;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\deploy_steps\Attribute\DeployStep;
use Drupal\deploy_steps\DeployStepBase;
use Drupal\deploy_steps\DeployStepInterface;
use Drupal\deploy_steps\DrushTrait;
use Drupal\deploy_steps\EnvTrait;

/**
 * Imports migrations on every deploy by redispatching `migrate:import`.
 *
 * Demonstrates two things. The bulk-work pattern: `migrate:import` builds a
 * Drupal batch that Drush processes across restarting `batch:process`
 * subprocesses, so a large import stays within memory bounds. And reading
 * deploy-time configuration from environment variables, the way a deploy
 * pipeline passes settings into PHP - `DRUPAL_MIGRATION_SKIP=1` skips the step,
 * while `DRUPAL_MIGRATION_IMPORT_LIMIT` and `DRUPAL_MIGRATION_UPDATE` shape the
 * `migrate:import` options.
 */
#[DeployStep(
  id: 'import_migrations',
  label: new TranslatableMarkup('Import migrations'),
  weight: 10,
  phase: DeployStepInterface::PHASE_POST,
)]
class ImportMigrationsDeployStep extends DeployStepBase {

  use DrushTrait;
  use EnvTrait;

  /**
   * {@inheritdoc}
   */
  public function skip(): ?string {
    if ($this->env('DRUPAL_MIGRATION_SKIP', '0') === '1') {
      return 'DRUPAL_MIGRATION_SKIP is set';
    }

    // `migrate:import` is provided by the migrate_tools module.
    return $this->moduleHandler->moduleExists('migrate_tools') ? NULL : 'migrate_tools module is not enabled';
  }

  /**
   * {@inheritdoc}
   */
  public function run(): void {
    $options = ['all' => TRUE];

    // A limit of 0 imports everything; any positive value caps the batch.
    $limit = (int) $this->env('DRUPAL_MIGRATION_IMPORT_LIMIT', '50');

    if ($limit > 0) {
      $options['limit'] = $limit;
    }

    if ($this->env('DRUPAL_MIGRATION_UPDATE', '0') === '1') {
      $options['update'] = TRUE;
    }

    $this->drush('migrate:import', [], $options);
  }

}
