<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\entity_usage\Controller\ListUsageController;

/**
 * Filter form for the entity usage listing page.
 *
 * Submits via GET, so the filter state lives entirely in the URL and this
 * form is never actually processed by the form API; the listing page just
 * reads the "list_old_revisions" query argument itself. The checkbox also
 * carries HTMX attributes so, when available, toggling it swaps the listing
 * in place instead of a full page reload; the plain GET submission (with a
 * visible submit button) is the fallback when JavaScript isn't available.
 */
class EntityUsageFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_usage_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#method'] = 'get';
    // The listing is reachable from more than one route (the admin page, and
    // the "Usage" local task on the entity itself), so submit back to
    // whichever one is currently being viewed rather than hardcoding one.
    $current_url = Url::fromRoute('<current>');
    $form['#action'] = $current_url->toString();

    // form_build_id/form_token/form_id would otherwise end up as query
    // arguments, both on a plain GET submission and (since HTMX includes the
    // closest form's fields by default) on the HTMX request below; neither
    // needs them, since this form is never actually processed by the form
    // API.
    $form['#after_build'][] = '::removeFormMetadata';

    $form['list_old_revisions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show usage in old revisions'),
      '#default_value' => $this->getRequest()->query->getBoolean('list_old_revisions'),
    ];
    (new Htmx())
      ->get($current_url)
      ->trigger('change')
      ->target('#' . ListUsageController::HTMX_LIST_ID)
      ->select('#' . ListUsageController::HTMX_LIST_ID)
      ->swap('outerHTML')
      ->pushUrl(TRUE)
      ->onlyMainContent()
      ->applyTo($form['list_old_revisions']);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      // Prevent op from showing up in the query string, and hide the button
      // itself: with JavaScript available, the checkbox above submits on
      // change via HTMX instead.
      '#name' => '',
      '#attributes' => ['class' => ['js-hide']],
    ];

    return $form;
  }

  /**
   * Removes form-processing metadata inputs so they never end up in the URL.
   *
   * @param mixed[] $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed[]
   *   The form structure, with metadata inputs removed.
   */
  public function removeFormMetadata(array $form, FormStateInterface $form_state): array {
    unset($form['form_build_id'], $form['form_token'], $form['form_id']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // This form submits via GET to the listing page itself; processing
    // happens there.
  }

}
