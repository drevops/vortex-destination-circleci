<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Form hook implementations for entity_usage.
 */
class EntityUsageFormHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityUsageInterface $entityUsage,
  ) {

  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $form_object->getEntity();
    if (empty($entity)) {
      return;
    }
    $entity_type_id = $entity->getEntityTypeId();
    $config = $this->configFactory->get('entity_usage.settings');
    // Add the configuration cache tag to rebuild forms when the config changes.
    $metadata = CacheableMetadata::createFromRenderArray($form);
    $metadata = $metadata->merge(CacheableMetadata::createFromObject($config));
    $metadata->applyTo($form);
    $edit_entity_types = $config->get('edit_warning_message_entity_types') ?: [];
    $delete_entity_types = $config->get('delete_warning_message_entity_types') ?: [];
    // Abort early if this entity is not configured to show any message.
    if (!in_array($entity_type_id, $edit_entity_types, TRUE) && !in_array($entity_type_id, $delete_entity_types, TRUE)) {
      return;
    }
    $is_edit_form = $form_object->getOperation() === 'edit' && in_array($entity_type_id, $edit_entity_types, TRUE);
    $is_delete_form = FALSE;
    if (!$is_edit_form && in_array($entity_type_id, $delete_entity_types, TRUE)) {
      // Even if this is not on the UI, sites can define additional form classes
      // where the delete message can be shown.
      $form_classes = $config->get('delete_warning_form_classes') ?: [
        'Drupal\Core\Entity\ContentEntityDeleteForm',
      ];
      foreach ($form_classes as $class) {
        if ($form_object instanceof $class) {
          $is_delete_form = TRUE;
          break;
        }
      }
    }
    if (!$is_edit_form && !$is_delete_form) {
      return;
    }
    // As we now depend on usage data these forms are uncacheable.
    $metadata = $metadata->setCacheMaxAge(0);
    $metadata->applyTo($form);
    // If there are no usages, there is nothing to do.
    if (empty($this->entityUsage->listSources($entity, TRUE, 1))) {
      return;
    }
    $local_task_entity_types = $config->get('local_task_enabled_entity_types');
    $usage_url = in_array($entity_type_id, $local_task_entity_types, TRUE) ? Url::fromRoute("entity.$entity_type_id.entity_usage", [
      $entity_type_id => $entity->id(),
    ], ['query' => ['list_old_revisions' => 1]]) : Url::fromRoute('entity_usage.usage_list', [
      'entity_type' => $entity_type_id,
      'entity_id' => $entity->id(),
    ], ['query' => ['list_old_revisions' => 1]]);
    // Check for the edit warning.
    if ($is_edit_form) {
      $form['entity_usage_edit_warning'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            $this->t('Modifications on this form will affect all <a href="@usage_url" target="_blank">existing usages</a> of this entity.', [
              '@usage_url' => $usage_url->toString(),
            ]),
          ],
        ],
        '#status_headings' => [
          'warning' => $this->t('Warning message'),
        ],
        '#weight' => -201,
      ];
    }
    elseif ($is_delete_form) {
      $form['entity_usage_delete_warning'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            $this->t('There are <a href="@usage_url" target="_blank">recorded usages</a> of this entity.', [
              '@usage_url' => $usage_url->toString(),
            ]),
          ],
        ],
        '#status_headings' => [
          'warning' => $this->t('Warning message'),
        ],
        '#weight' => -201,
      ];
    }
  }

}
