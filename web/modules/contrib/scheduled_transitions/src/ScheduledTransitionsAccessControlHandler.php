<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Access control handler for scheduled transitions.
 */
class ScheduledTransitionsAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $entity */
    $access = parent::checkAccess($entity, $operation, $account);

    if ($access->isNeutral()) {
      $for = $entity->getEntity();
      if ($for !== NULL) {
        // Defer access to associated entity.
        return $for->access($operation, $account, TRUE);
      }
    }

    if ($operation === ScheduledTransitionInterface::ENTITY_OPERATION_RESCHEDULE) {
      $for = $entity->getEntity();
      if ($for !== NULL) {
        // Defer access to associated entity.
        $access = $access->andIf($for->access(ScheduledTransitionsPermissions::ENTITY_OPERATION_RESCHEDULE_TRANSITIONS, $account, TRUE));
      }
    }

    if (\in_array($operation, [
      'update',
      'delete',
      ScheduledTransitionInterface::ENTITY_OPERATION_RESCHEDULE,
    ], TRUE)) {
      $access = $access->orIf(
        AccessResult::forbiddenIf($entity->isProcessed(), \sprintf('Cannot `%s` when Scheduled Transition has been processed.', $operation))->addCacheableDependency($entity),
      );
    }

    return $access;
  }

}
