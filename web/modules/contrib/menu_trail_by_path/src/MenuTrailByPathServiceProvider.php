<?php

namespace Drupal\menu_trail_by_path;

use \Drupal\Core\DependencyInjection\ServiceProviderBase;
use \Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Overrides the class for the menu link tree.
 */
class MenuTrailByPathServiceProvider extends ServiceProviderBase {
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('menu.active_trail');
    $definition->setClass('Drupal\menu_trail_by_path\MenuTrailByPathActiveTrail');
    $definition->addMethodCall('setPathValidator', [new Reference('path.validator')]);
    $definition->addMethodCall('setRequestContext', [new Reference('router.request_context')]);
    $definition->addMethodCall('setLanguageManager', [new Reference('language_manager')]);
    $definition->addMethodCall('setConfigFactory', [new Reference('config.factory')]);
  }
}
