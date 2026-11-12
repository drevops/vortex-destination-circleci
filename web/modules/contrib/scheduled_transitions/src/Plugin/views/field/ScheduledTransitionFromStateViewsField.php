<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Scheduled transition from state.
 *
 * @ViewsField(\Drupal\scheduled_transitions\Plugin\views\field\ScheduledTransitionFromStateViewsField::PLUGIN_ID)
 */
class ScheduledTransitionFromStateViewsField extends FieldPluginBase {

  public const PLUGIN_ID = 'scheduled_transitions_transition_from';

  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  public function query(): void {
    // Do nothing.
  }

  public function render(ResultRow $values): MarkupInterface|string {
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition */
    $scheduledTransition = $this->getEntity($values);

    $entity = $scheduledTransition->getEntity();
    if ($entity === NULL) {
      return '';
    }

    $workflowPlugin = $scheduledTransition->getWorkflow()?->getTypePlugin();
    $workflowStates = $workflowPlugin?->getStates() ?? [];

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    $entityRevisionId = $scheduledTransition->getEntityRevisionId();
    if (\is_numeric($entityRevisionId) && $entityRevisionId > 0) {
      $entityRevision = $entityStorage->loadRevision($entityRevisionId);

      $revisionTArgs = ['@revision_id' => $entityRevisionId];
      if ($entityRevision !== NULL) {
        $fromState = $workflowStates[$entityRevision->moderation_state->value] ?? NULL;
        return $fromState?->label() ?? $this->t('- Missing from workflow/state -');
      }
      else {
        return $this->t('Deleted revision #@revision_id', $revisionTArgs);
      }
    }
    else {
      return '';
    }
  }

}
