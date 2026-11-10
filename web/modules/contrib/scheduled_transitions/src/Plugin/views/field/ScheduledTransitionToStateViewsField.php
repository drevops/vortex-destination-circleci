<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Scheduled transition from state.
 *
 * @ViewsField(\Drupal\scheduled_transitions\Plugin\views\field\ScheduledTransitionToStateViewsField::PLUGIN_ID)
 */
class ScheduledTransitionToStateViewsField extends FieldPluginBase {

  public const PLUGIN_ID = 'scheduled_transitions_transition_to';

  public function render(ResultRow $values): string|MarkupInterface {
    $value = $this->getValue($values);

    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition */
    $scheduledTransition = $this->getEntity($values);

    $workflowPlugin = $scheduledTransition->getWorkflow()?->getTypePlugin();
    $workflowStates = $workflowPlugin?->getStates() ?? [];

    $fromState = $workflowStates[$value] ?? NULL;
    return $fromState?->label() ?? $this->t('- Missing to workflow/state -');
  }

}
