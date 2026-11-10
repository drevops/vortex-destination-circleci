<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Defines local action class.
 */
class ScheduledTransitionsLocalAction extends LocalActionDefault {

  public function getOptions(RouteMatchInterface $route_match): array {
    $options = parent::getOptions($route_match);
    $options['query']['destination'] = Url::fromRoute('<current>')->toString();
    return $options;
  }

  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.path';
    return $contexts;
  }

}
