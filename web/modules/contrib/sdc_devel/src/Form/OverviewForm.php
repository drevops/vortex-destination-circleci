<?php

declare(strict_types=1);

namespace Drupal\sdc_devel\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\State\StateInterface;

/**
 * Provides a SDC Devel form.
 *
 * @codeCoverageIgnore
 */
final class OverviewForm extends FormBase {

  use AutowireTrait;

  /**
   * Construct the Overview form.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal state service.
   */
  public function __construct(private readonly StateInterface $state) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sdc_devel_overview';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $errors
   *   The current errors of the component.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $errors = []): array {
    $options = RfcLogLevel::getLevels();
    // Only keep our levels.
    unset($options[0], $options[1], $options[6], $options[7]);

    foreach ($options as $level => $name) {
      $options[$level] = \sprintf('%s (%s)', $name, $errors[$level] ?? 0);
    }
    krsort($options);

    $values = $this->state->get('sdc_devel_overview_severity', array_keys($options));

    $form['levels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select the severity to display'),
      '#options' => $options,
      '#required' => TRUE,
      '#multiple' => TRUE,
      '#default_value' => $values,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#name' => 'filter',
        '#value' => $this->t('Filter'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->state->set('sdc_devel_overview_severity', $form_state->getValue('levels'));
  }

}
