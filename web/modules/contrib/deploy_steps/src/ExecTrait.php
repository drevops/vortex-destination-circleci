<?php

declare(strict_types=1);

namespace Drupal\deploy_steps;

use Symfony\Component\Process\Process;

/**
 * Runs an external command in its own process.
 *
 * Opt-in capability for steps that shell out to a non-Drush program; compose it
 * with `use ExecTrait;` and call ::exec(). For redispatching a Drush
 * sub-command instead, use \Drupal\deploy_steps\DrushTrait.
 */
trait ExecTrait {

  /**
   * Runs an external command in its own process.
   *
   * Output is streamed to the deploy log and a non-zero exit throws - aborting
   * the deploy - mirroring DrushTrait::drush()'s contract.
   *
   * @param string $command
   *   The command (program) to run, e.g. '/path/to/post-deploy.sh'.
   * @param array $arguments
   *   Positional arguments appended to the command; cast to strings.
   * @param array $inputs
   *   Lines fed to the process on standard input; empty for none.
   * @param array $env
   *   Additional environment variables for the process.
   * @param int $timeout
   *   Overall timeout in seconds; 0 disables it (for long-running work).
   * @param int $idle_timeout
   *   Idle timeout in seconds; 0 disables it.
   *
   * @return \Symfony\Component\Process\Process
   *   The completed process.
   *
   * @codeCoverageIgnore
   */
  protected function exec(
    string $command,
    array $arguments = [],
    array $inputs = [],
    array $env = [],
    int $timeout = 60,
    int $idle_timeout = 30,
  ): Process {
    $command_line = [$command];

    foreach ($arguments as $argument) {
      $command_line[] = (string) $argument;
    }

    $input = $inputs === [] ? NULL : implode(PHP_EOL, $inputs) . PHP_EOL;

    $process = new Process($command_line, NULL, $env, $input, $timeout);
    $process->setIdleTimeout($idle_timeout);

    $process->mustRun(static function (string $type, string $buffer): void {
      echo $buffer;
    });

    return $process;
  }

}
