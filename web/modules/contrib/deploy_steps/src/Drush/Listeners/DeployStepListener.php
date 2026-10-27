<?php

declare(strict_types=1);

namespace Drupal\deploy_steps\Drush\Listeners;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Drupal\deploy_steps\DeployStepInterface;
use Drupal\deploy_steps\DeployStepRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Runs deploy step plugins around a Symfony Console `deploy:hook` command.
 *
 * Drush 14 implements `deploy:hook` as a Console command, which annotated
 * command hooks do not reach. This listener runs the phases from the Console
 * command and terminate events instead. An annotated `deploy:hook` is skipped,
 * since DeployStepCommands already hooks it.
 */
#[AsEventListener(method: 'runPreDeploySteps')]
#[AsEventListener(method: 'runPostDeploySteps')]
final readonly class DeployStepListener {

  /**
   * Constructs a DeployStepListener object.
   *
   * @param \Drupal\deploy_steps\DeployStepRunner $deployStepRunner
   *   The deploy step runner.
   */
  public function __construct(
    protected DeployStepRunner $deployStepRunner,
  ) {}

  /**
   * Creates an instance of the listener.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return self
   *   The listener instance.
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get(DeployStepRunner::class));
  }

  /**
   * Runs PRE-phase plugins before a Console `deploy:hook` command.
   *
   * @param \Symfony\Component\Console\Event\ConsoleCommandEvent $event
   *   The event dispatched before the command runs.
   */
  public function runPreDeploySteps(ConsoleCommandEvent $event): void {
    if (!$this->isConsoleDeployHook($event->getCommand())) {
      return;
    }

    $this->deployStepRunner->run(DeployStepInterface::PHASE_PRE);
  }

  /**
   * Runs POST-phase plugins after a Console `deploy:hook` command succeeds.
   *
   * @param \Symfony\Component\Console\Event\ConsoleTerminateEvent $event
   *   The event dispatched after the command runs.
   */
  public function runPostDeploySteps(ConsoleTerminateEvent $event): void {
    // Post-phase steps assume the deploy hooks completed, so a failed
    // `deploy:hook` skips them.
    if ($event->getExitCode() !== Command::SUCCESS || !$this->isConsoleDeployHook($event->getCommand())) {
      return;
    }

    $this->deployStepRunner->run(DeployStepInterface::PHASE_POST);
  }

  /**
   * Checks whether a command is `deploy:hook` implemented as a Console command.
   *
   * @param \Symfony\Component\Console\Command\Command|null $command
   *   The command, or NULL when the event carries none.
   *
   * @return bool
   *   TRUE for a Console `deploy:hook` command, FALSE otherwise.
   */
  protected function isConsoleDeployHook(?Command $command): bool {
    return $command instanceof Command && !$command instanceof AnnotatedCommand && $command->getName() === 'deploy:hook';
  }

}
