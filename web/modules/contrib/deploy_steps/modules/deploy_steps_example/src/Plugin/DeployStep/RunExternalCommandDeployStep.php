<?php

declare(strict_types=1);

namespace Drupal\deploy_steps_example\Plugin\DeployStep;

use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\deploy_steps\Attribute\DeployStep;
use Drupal\deploy_steps\DeployStepBase;
use Drupal\deploy_steps\DeployStepInterface;
use Drupal\deploy_steps\EnvironmentTrait;
use Drupal\deploy_steps\ExecTrait;

/**
 * Runs an external command on every deploy.
 *
 * Demonstrates two capability traits at once: EnvironmentTrait to gate the step
 * by environment, and ExecTrait to call something outside Drupal and Drush. The
 * step skips on the local environment, and when the command (read from
 * $settings['deploy_steps_example_command']) is unset or missing - so enabling
 * the module never breaks a deploy on its own. ExecTrait::exec() runs the
 * command through Symfony's Process, streaming output and throwing on a
 * non-zero exit to abort the deploy.
 */
#[DeployStep(
  id: 'run_external_command',
  label: new TranslatableMarkup('Run external deploy command'),
  weight: 30,
  phase: DeployStepInterface::PHASE_POST,
)]
class RunExternalCommandDeployStep extends DeployStepBase {

  use EnvironmentTrait;
  use ExecTrait;

  /**
   * {@inheritdoc}
   */
  public function skip(): ?string {
    if ($this->environment() === 'local') {
      return 'local environment';
    }

    $command = $this->commandPath();

    if ($command === '') {
      return 'no external deploy command configured';
    }

    if (!is_file($command)) {
      return sprintf('external deploy command not found: %s', $command);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function run(): void {
    $this->exec($this->commandPath());
  }

  /**
   * Returns the configured external deploy command path.
   *
   * @return string
   *   The path to the command, or an empty string when unset.
   *
   * @SuppressWarnings("PHPMD.StaticAccess")
   */
  protected function commandPath(): string {
    return (string) Settings::get('deploy_steps_example_command', '');
  }

}
