<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\scheduled_transitions\Plugin\Menu\LocalAction\ScheduledTransitionsLocalAction;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Scheduled transitions actions for entities.
 */
class ScheduledTransitionsLocalActions extends DeriverBase implements ContainerDeriverInterface {

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

    $scheduledTransitionDefinition = $this->entityTypeManager->getDefinition('scheduled_transition');
    foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
      if ($entityType->hasLinkTemplate(ScheduledTransitionsRouteProvider::LINK_TEMPLATE)) {
        $entityTypeId = $entityType->id();
        $this->derivatives["$entityTypeId.add_scheduled_transition"] = [
          'route_name' => ScheduledTransitionsRouteProvider::getScheduledTransitionAddRouteName($entityType),
          'appears_on' => [ScheduledTransitionsRouteProvider::getScheduledTransitionRouteName($entityType)],
          'class' => ScheduledTransitionsLocalAction::class,
          'title' => $this->t('Add @singular', [
            '@singular' => $scheduledTransitionDefinition->getSingularLabel(),
          ]),
          'options' => [
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode([
                'width' => '80%',
              ]),
            ],
          ],
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
