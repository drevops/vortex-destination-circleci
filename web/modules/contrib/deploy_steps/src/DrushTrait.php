<?php

declare(strict_types=1);

namespace Drupal\deploy_steps;

use Consolidation\SiteProcess\SiteProcess;
use Drush\Drush;

/**
 * Redispatches a Drush sub-command in its own process.
 *
 * Opt-in capability for steps that run heavy or long-running Drush work;
 * compose it with `use DrushTrait;` and call ::drush().
 */
trait DrushTrait {

  /**
   * Runs a drush sub-command in its own process.
   *
   * Use this for heavy or long-running work (migrations, source-DB import, bulk
   * reindex): the sub-command runs in a fresh process with its own memory
   * ceiling and bootstrap, output is streamed to the deploy log, the timeout is
   * disabled, and a non-zero exit throws - aborting the deploy.
   *
   * Memory/timeout safety for the long-running case is owned by the invoked
   * command: a command that builds a Drupal batch (migrate:import,
   * search-api:index) is processed by Drush across subprocesses that restart as
   * memory fills up, the same way a sandboxed hook_update_N() is re-entered.
   *
   * @param string $command
   *   The drush command name, e.g. 'migrate:import'.
   * @param array $args
   *   Positional command arguments.
   * @param array $options
   *   Command options, e.g. ['limit' => 50].
   *
   * @return \Consolidation\SiteProcess\SiteProcess
   *   The completed process.
   *
   * @SuppressWarnings("PHPMD.StaticAccess")
   *
   * @codeCoverageIgnore
   */
  protected function drush(string $command, array $args = [], array $options = []): SiteProcess {
    $process = Drush::drush(Drush::aliasManager()->getSelf(), $command, $args, $options + Drush::redispatchOptions());
    $process->setTimeout(NULL);
    $process->mustRun($process->showRealtime());

    return $process;
  }

}
