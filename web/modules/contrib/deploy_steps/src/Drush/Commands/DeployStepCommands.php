<?php

declare(strict_types=1);

namespace Drupal\deploy_steps\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\deploy_steps\DeployStepInterface;
use Drupal\deploy_steps\DeployStepRunner;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs deploy step plugins around every `drush deploy:hook`.
 *
 * Run-once hooks (hook_update_N(), hook_post_update_NAME(), hook_deploy_NAME())
 * never run twice, so they cannot express "run on every deploy". This command
 * fills that gap: deploy_steps owns the single pair of pre/post command hooks
 * on `deploy:hook`, and on every deploy the runner discovers every DeployStep
 * plugin from every enabled module, orders each phase by weight, and runs the
 * open ones - pre-phase before the `deploy:hook` body, post-phase after it.
 *
 * The README ("Why this module exists", "How it runs") covers the discovery
 * design and why the anchor is `deploy:hook` rather than `deploy`.
 */
final class DeployStepCommands extends DrushCommands {

  /**
   * Constructs a DeployStepCommands object.
   *
   * @param \Drupal\deploy_steps\DeployStepRunner $deployStepRunner
   *   The deploy step runner.
   */
  public function __construct(
    protected readonly DeployStepRunner $deployStepRunner,
  ) {
    parent::__construct();
  }

  /**
   * Creates an instance of the command handler.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return self
   *   The command handler instance.
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get(DeployStepRunner::class));
  }

  /**
   * Runs PRE-phase plugins before EVERY `drush deploy:hook`.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $command_data
   *   The command data.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: 'deploy:hook')]
  public function runPreDeploySteps(CommandData $command_data): void {
    $this->deployStepRunner->run(DeployStepInterface::PHASE_PRE);
  }

  /**
   * Runs POST-phase plugins after EVERY `drush deploy:hook`.
   *
   * @param mixed $result
   *   The result returned by the `deploy:hook` command.
   * @param \Consolidation\AnnotatedCommand\CommandData $command_data
   *   The command data.
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'deploy:hook')]
  public function runPostDeploySteps(mixed $result, CommandData $command_data): void {
    $this->deployStepRunner->run(DeployStepInterface::PHASE_POST);
  }

}
