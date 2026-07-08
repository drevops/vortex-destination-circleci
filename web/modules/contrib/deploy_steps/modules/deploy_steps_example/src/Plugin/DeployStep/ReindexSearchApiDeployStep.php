<?php

declare(strict_types=1);

namespace Drupal\deploy_steps_example\Plugin\DeployStep;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\deploy_steps\Attribute\DeployStep;
use Drupal\deploy_steps\DeployStepBase;
use Drupal\deploy_steps\DeployStepInterface;
use Drupal\deploy_steps\DrushTrait;

/**
 * Indexes pending Search API items on every deploy via `search-api:index`.
 *
 * A second bulk-work example: `search-api:index` builds a Drupal batch, which
 * Drush processes across `batch:process` subprocesses that restart as memory
 * fills, so even a large backlog indexes within memory bounds. Indexing the
 * pending items on every deploy is idempotent.
 */
#[DeployStep(
  id: 'reindex_search_api',
  label: new TranslatableMarkup('Index pending Search API items'),
  weight: 20,
  phase: DeployStepInterface::PHASE_POST,
)]
class ReindexSearchApiDeployStep extends DeployStepBase {

  use DrushTrait;

  /**
   * {@inheritdoc}
   */
  public function skip(): ?string {
    // `search-api:index` is provided by the search_api module.
    return $this->moduleHandler->moduleExists('search_api') ? NULL : 'search_api module is not enabled';
  }

  /**
   * {@inheritdoc}
   */
  public function run(): void {
    $this->drush('search-api:index', [], ['batch-size' => 100]);
  }

}
