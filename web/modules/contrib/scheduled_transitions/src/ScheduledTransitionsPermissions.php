<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Generates permissions for host entity types for scheduled transitions.
 */
class ScheduledTransitionsPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use AutowireTrait;

  /**
   * Entity operation for viewing transitions for an individual entity.
   */
  public const ENTITY_OPERATION_VIEW_TRANSITIONS = 'view scheduled transition';

  /**
   * Entity operation for adding transitions to an individual entity.
   */
  public const ENTITY_OPERATION_ADD_TRANSITION = 'add scheduled transition';

  /**
   * Entity operation for rescheduling all transitions for an individual entity.
   */
  public const ENTITY_OPERATION_RESCHEDULE_TRANSITIONS = 'reschedule scheduled transitions';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    TranslationInterface $stringTranslation,
    protected ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Generate dynamic permissions.
   */
  public function permissions(): array {
    $permissions = [];

    $bundleInfo = $this->scheduledTransitionsUtility->getBundles();
    foreach ($bundleInfo as $entityTypeId => $bundles) {
      $entityBundleInfo = $this->bundleInfo->getBundleInfo($entityTypeId);
      foreach ($bundles as $bundleId) {
        $tArgs = [
          '@entity_type' => $this->entityTypeManager->getDefinition($entityTypeId)->getLabel(),
          '@bundle' => $entityBundleInfo[$bundleId]['label'] ?? '',
        ];

        $viewPermission = static::viewScheduledTransitionsPermission($entityTypeId, $bundleId);
        $permissions[$viewPermission] = [
          'title' => $this->t('View scheduled transitions for @entity_type:@bundle entities', $tArgs),
        ];

        $addPermission = static::addScheduledTransitionsPermission($entityTypeId, $bundleId);
        $permissions[$addPermission] = [
          'title' => $this->t('Add scheduled transitions for @entity_type:@bundle entities', $tArgs),
        ];

        $reschedulePermission = static::rescheduleScheduledTransitionsPermission($entityTypeId, $bundleId);
        $permissions[$reschedulePermission] = [
          'title' => $this->t('Reschedule scheduled transitions for @entity_type:@bundle entities', $tArgs),
        ];
      }
    }

    return $permissions;
  }

  public static function viewScheduledTransitionsPermission(string $entityTypeId, string $bundle): string {
    return \sprintf('view scheduled transitions %s %s', $entityTypeId, $bundle);
  }

  public static function addScheduledTransitionsPermission(string $entityTypeId, string $bundle): string {
    return \sprintf('add scheduled transitions %s %s', $entityTypeId, $bundle);
  }

  public static function rescheduleScheduledTransitionsPermission(string $entityTypeId, string $bundle): string {
    return \sprintf('reschedule scheduled transitions %s %s', $entityTypeId, $bundle);
  }

}
