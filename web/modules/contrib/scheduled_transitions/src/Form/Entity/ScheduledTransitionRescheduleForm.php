<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Form\Entity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * @method \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface getEntity()
 */
class ScheduledTransitionRescheduleForm extends ContentEntityForm {

  use AutowireTrait;

  final public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  public function getBaseFormId(): ?string {
    return NULL;
  }

  public function form(array $form, FormStateInterface $form_state): array {
    $scheduledTransition = $this->getEntity();

    $currentDate = new \DateTime('@' . $scheduledTransition->getTransitionTime());
    $form['current_date'] = [
      '#title' => $this->t('Current execution date'),
      '#description' => $this->t('This transition is currently scheduled to execute on this date.'),
      '#type' => 'datetime',
      '#default_value' => DrupalDateTime::createFromDateTime($currentDate),
      '#disabled' => TRUE,
    ];

    $form['date'] = [
      '#title' => $this->t('New execution date'),
      '#description' => $this->t('Change transition to execute on this date.'),
      '#type' => 'datetime',
      '#default_value' => DrupalDateTime::createFromDateTime($currentDate),
      '#required' => TRUE,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Datetime\DrupalDateTime $date */
    $date = $form_state->getValue(['date']);

    $scheduledTransition = $this->getEntity();
    $scheduledTransition
      ->setTransitionTime($date->getTimestamp());

    $this->messenger()->addMessage($this->t('Rescheduled transition for @date', [
      '@date' => $this->dateFormatter->format($date->getTimestamp()),
    ]));
  }

  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = [];
    $actions['submit']['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reschedule transition'),
      '#submit' => ['::submitForm', '::save'],
    ];
    return $actions;
  }

}
