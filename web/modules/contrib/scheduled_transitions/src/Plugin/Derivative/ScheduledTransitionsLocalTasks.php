<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\scheduled_transitions\Plugin\Menu\LocalTask\ScheduledTransitionsLocalTask;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Scheduled transitions tab for entities.
 *
 * @phpstan-import-type Definition from \Drupal\scheduled_transitions\Plugin\Menu\LocalTask\ScheduledTransitionsLocalTask
 */
class ScheduledTransitionsLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  protected string $basePluginId;
  protected EntityTypeManagerInterface $entityTypeManager;

  final public function __construct(
    string $base_plugin_id,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $base_plugin_id,
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
      if ($entityType->hasLinkTemplate(ScheduledTransitionsRouteProvider::LINK_TEMPLATE)) {
        $entityTypeId = $entityType->id();
        /** @var Definition $definition */
        $definition = [
          'class' => ScheduledTransitionsLocalTask::class,
          'route_name' => ScheduledTransitionsRouteProvider::getScheduledTransitionRouteName($entityType),
          // Title is overridden by class.
          'title' => $this->t('Scheduled transitions'),
          'base_route' => "entity.$entityTypeId.canonical",
          // Weight it after nodes' Edit, Delete, Versions.
          'weight' => 30,
        ] + $base_plugin_definition;
        $this->derivatives["$entityTypeId.scheduled_transitions"] = $definition;
      }
    }

    return $this->derivatives;
  }

}
