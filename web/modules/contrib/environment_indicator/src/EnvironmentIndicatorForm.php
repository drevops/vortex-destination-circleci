<?php

namespace Drupal\environment_indicator;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form field specifications.
 */
class EnvironmentIndicatorForm extends EntityForm {

  /**
   * The form for the environment switcher.
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\environment_indicator\Entity\EnvironmentIndicator $environment_switcher */
    $environment_switcher = $this->getEntity();

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $environment_switcher->label(),
    ];
    $form['machine'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'source' => ['name'],
        'exists' => 'environment_indicator_load',
      ],
      '#default_value' => $environment_switcher->id(),
      '#disabled' => !empty($environment_switcher->machine),
    ];
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('The Base URL'),
      '#description' => $this->t('The base URL to switch to. Example: <code>https://example.com</code>.'),
      '#default_value' => $environment_switcher->getUrl(),
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $environment_switcher->getWeight(),
    ];

    $form['bg_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#description' => $this->t('Background color for the environment switcher. Ex: #0d0d0d.'),
      '#default_value' => $environment_switcher->getBgColor() ?: '#0d0d0d',
    ];
    $form['fg_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#description' => $this->t('Color for the environment switcher. Ex: #d0d0d0.'),
      '#default_value' => $environment_switcher->getFgColor() ?: '#d0d0d0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Save your config entity.
   *
   * There will eventually be default code to rely on here, but it doesn't exist
   * yet.
   */
  public function save(array $form, FormStateInterface $form_state) {
    $environment = $this->getEntity();
    $return = $environment->save();
    $this->messenger()->addMessage($this->t('Saved the %label environment.', [
      '%label' => $environment->label(),
    ]));

    $form_state->setRedirect('entity.environment_indicator.collection');

    return $return;
  }

  /**
   * Strips trailing slashes from the URL value.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Get the URL value, strip *all* trailing slashes, and set it back.
    $url = $form_state->getValue('url');
    if (!empty($url)) {
      $url = rtrim($url, '/');
      $form_state->setValue('url', $url);
    }
  }

}
