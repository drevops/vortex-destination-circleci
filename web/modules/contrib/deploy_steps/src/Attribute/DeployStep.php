<?php

declare(strict_types=1);

namespace Drupal\deploy_steps\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\deploy_steps\DeployStepInterface;

/**
 * Defines a deploy step plugin.
 *
 * A deploy step plugin is a unit of idempotent work that runs on every
 * `drush deploy:hook` - the repeatable counterpart to run-once
 * hook_deploy_NAME(). Place the plugin class in any enabled module's
 * `Plugin/DeployStep/` namespace; the deploy_steps runner discovers it, orders
 * it by weight within its phase, checks its skip reason, and runs it.
 *
 * @code
 * #[DeployStep(
 *   id: 'rebuild_search_index',
 *   label: new TranslatableMarkup('Rebuild search index'),
 *   weight: 10,
 *   phase: DeployStepInterface::PHASE_POST,
 * )]
 * final class RebuildSearchIndex extends DeployStepBase {
 *   public function run(): void {
 *     // ...
 *   }
 * }
 * @endcode
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DeployStep extends AttributeBase {

  /**
   * Constructs a DeployStep attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $label
   *   A human-readable label, shown in deploy logs.
   * @param int $weight
   *   Run order within the phase; steps run in ascending weight (lower first).
   * @param string $phase
   *   The deploy phase: DeployStepInterface::PHASE_PRE (before the deploy hook
   *   body) or ::PHASE_POST (after it, the default).
   */
  public function __construct(
    string $id,
    public readonly mixed $label = NULL,
    public readonly int $weight = 0,
    public readonly string $phase = DeployStepInterface::PHASE_POST,
  ) {
    parent::__construct($id);
  }

}
