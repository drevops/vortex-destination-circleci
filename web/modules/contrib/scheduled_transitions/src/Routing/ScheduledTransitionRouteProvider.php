<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route provider for Scheduled Transition entities.
 */
class ScheduledTransitionRouteProvider extends DefaultHtmlRouteProvider {

  public function getRoutes(EntityTypeInterface $entity_type): RouteCollection {
    $collection = parent::getRoutes($entity_type);

    if (($route = $this->getRescheduleFormRoute($entity_type)) !== NULL) {
      $collection->add('entity.scheduled_transition.reschedule_form', $route);
    }

    return $collection;
  }

  /**
   * Gets the reschedule-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRescheduleFormRoute(EntityTypeInterface $entityType): ?Route {
    // We check if it exists in case a third party has unset it.
    if ($entityType->hasLinkTemplate('reschedule-form')) {
      $entityTypeId = $entityType->id();
      // PHPStan should know better since hasLinkTemplate called above.
      // @phpstan-ignore-next-line argument.type
      $route = new Route($entityType->getLinkTemplate('reschedule-form'));
      $route
        ->addDefaults([
          '_entity_form' => "{$entityTypeId}.reschedule",
          '_title' => 'Reschedule transition',
        ])
        ->setRequirement('_entity_access', "{$entityTypeId}.reschedule")
        ->setRequirement($entityTypeId, '\d+')
        ->setOption('parameters', [
          $entityTypeId => ['type' => 'entity:' . $entityTypeId],
        ]);

      return $route;
    }

    return NULL;
  }

}
