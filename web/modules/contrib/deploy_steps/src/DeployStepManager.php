<?php

declare(strict_types=1);

namespace Drupal\deploy_steps;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\deploy_steps\Attribute\DeployStep;

/**
 * Plugin manager for deploy step plugins.
 *
 * Discovers DeployStep plugins from `Plugin/DeployStep/` in every enabled
 * module. Because discovery is keyed on the live enabled-module list, any
 * enabled module can contribute steps without registering its own Drush hook -
 * only deploy_steps needs to be wired into Drush.
 */
class DeployStepManager extends DefaultPluginManager {

  /**
   * Constructs a DeployStepManager object.
   *
   * @param \Traversable<string, string> $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/DeployStep', $namespaces, $module_handler, DeployStepInterface::class, DeployStep::class);

    $this->setCacheBackend($cache_backend, 'deploy_step_plugins', ['deploy_step_plugins']);
    $this->alterInfo('deploy_step_info');
  }

  /**
   * Returns deploy step instances for a phase, ordered by ascending weight.
   *
   * @param string $phase
   *   The phase to filter by: DeployStepInterface::PHASE_PRE or ::PHASE_POST.
   *
   * @return \Drupal\deploy_steps\DeployStepInterface[]
   *   Deploy step plugin instances for the phase, keyed by plugin ID.
   */
  public function getSortedSteps(string $phase): array {
    $definitions = array_filter($this->getDefinitions(), static fn(array $definition): bool => ($definition['phase'] ?? DeployStepInterface::PHASE_POST) === $phase);
    uasort($definitions, fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    $steps = [];
    foreach (array_keys($definitions) as $id) {
      $instance = $this->createInstance($id);
      if ($instance instanceof DeployStepInterface) {
        $steps[$id] = $instance;
      }
    }

    return $steps;
  }

}
