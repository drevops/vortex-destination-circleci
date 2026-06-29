<?php

declare(strict_types=1);

namespace Drupal\deploy_steps;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for deploy step plugins.
 *
 * Provides what every step needs: the weight/phase/label accessors from the
 * plugin definition, a default ::skip() that always runs, and the common Drupal
 * services injected on every step - the module handler, state, entity type
 * manager, and config factory - so most steps need no create() of their own. A
 * step needing another service overrides ::create() and calls parent::create().
 * Specialized capabilities are opt-in traits a step composes with `use`:
 * \Drupal\deploy_steps\EnvironmentTrait for environment-conditional skips,
 * \Drupal\deploy_steps\EnvTrait for reading environment variables,
 * \Drupal\deploy_steps\DrushTrait for redispatching a Drush sub-command, and
 * \Drupal\deploy_steps\ExecTrait for running an external command. Subclasses
 * implement ::run() and, when conditional, override ::skip().
 */
abstract class DeployStepBase extends PluginBase implements DeployStepInterface, ContainerFactoryPluginInterface {

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The state service.
   */
  protected StateInterface $state;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    // @phpstan-ignore new.static
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->state = $container->get('state');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function skip(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return (int) $this->definitionValue('weight', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getPhase(): string {
    return (string) $this->definitionValue('phase', self::PHASE_POST);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->definitionValue('label', $this->getPluginId());
  }

  /**
   * Reads a value from the plugin definition, falling back to a default.
   *
   * The plugin definition is an array for discovered plugins but may be an
   * object for other plugin types, so the array access is guarded in one place.
   *
   * @param string $key
   *   The plugin definition key to read.
   * @param mixed $default
   *   The value to return when the definition is not an array or lacks the key.
   *
   * @return mixed
   *   The definition value, or the default.
   */
  protected function definitionValue(string $key, mixed $default): mixed {
    return is_array($this->pluginDefinition) ? ($this->pluginDefinition[$key] ?? $default) : $default;
  }

}
