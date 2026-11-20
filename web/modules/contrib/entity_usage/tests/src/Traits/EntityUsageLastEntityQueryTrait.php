<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Traits;

use Drupal\Core\Entity\EntityInterface;

/**
 * Test trait providing helpers to query latest entities created.
 */
trait EntityUsageLastEntityQueryTrait {

  /**
   * Gets the latest entity created of a given type.
   *
   * Will fail the test if there is no entity of that type.
   *
   * @param string $entity_type_id
   *   The storage name of the entity.
   * @param bool $load
   *   (optional) Whether or not the return should be the loaded entity.
   *   Defaults to FALSE.
   *
   * @return int|string|EntityInterface
   *   The ID of the latest created entity of that type. If $load is TRUE, will
   *   use ::loadUnchanged() to get a fresh version of the entity object and
   *   return it.
   */
  protected function getLastEntityOfType(string $entity_type_id, bool $load = FALSE): int|string|EntityInterface {
    $query_result = \Drupal::entityQuery($entity_type_id)
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->accessCheck()
      ->execute();
    $entity_id = reset($query_result);
    if (empty($entity_id)) {
      $this->fail('Could not find latest entity of type: ' . $entity_type_id);
    }
    if ($load) {
      return \Drupal::entityTypeManager()
        ->getStorage($entity_type_id)
        ->loadUnchanged($entity_id);
    }
    else {
      return $entity_id;
    }
  }

}
