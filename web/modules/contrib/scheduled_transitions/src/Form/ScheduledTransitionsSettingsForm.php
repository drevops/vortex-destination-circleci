<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;
use Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface;

/**
 * Settings for scheduled transitions.
 */
class ScheduledTransitionsSettingsForm extends ConfigFormBase {


  use AutowireTrait;

  /**
   * Cache tag for scheduled transition settings.
   *
   * Features depending on settings from this form should add this tag for
   * invalidation.
   */
  public const SETTINGS_TAG = 'scheduled_transition_settings';

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected CacheTagsInvalidatorInterface $cacheTagInvalidator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  protected function getEditableConfigNames(): array {
    return ['scheduled_transitions.settings'];
  }

  public function getFormId(): string {
    return 'scheduled_transitions_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['bundles'] = [
      '#type' => 'details',
    ];
    $form['bundles']['help'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Enable entity type/bundles for use with scheduled transitions.'),
      '#suffix' => '</p>',
    ];
    $form['bundles']['enabled'] = [
      '#type' => 'tableselect',
      '#header' => [
        $this->t('Entity type'),
        $this->t('Bundle'),
        $this->t('Permissions'),
      ],
    ];

    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    $enabledBundles = [];
    foreach ($this->scheduledTransitionsUtility->getBundles() as $entityTypeId => $bundles) {
      foreach ($bundles as $bundle) {
        $enabledBundles[] = \sprintf('%s:%s', $entityTypeId, $bundle);
      }
    }

    // Flattens possible entity type/bundles.
    $possibleBundles = [];
    foreach ($this->scheduledTransitionsUtility->getApplicableBundles() as $entityTypeId => $bundles) {
      foreach ($bundles as $bundle) {
        $possibleBundles[] = \sprintf('%s:%s', $entityTypeId, $bundle);
      }
    }

    $form['bundles']['enabled']['#options'] = \array_map(function (string $bundle) use ($roles, $enabledBundles): array {
      $checked = \in_array($bundle, $enabledBundles, TRUE);
      [$entityTypeId, $bundle] = \explode(':', $bundle);

      // Stan thinks this isn't nullable...?
      /** @var \Drupal\Core\Entity\EntityTypeInterface|null $entityType */
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $bundleInfo = $this->bundleInfo->getBundleInfo($entityTypeId);

      // Show a handy permissions message, but only if this bundle is enabled.
      if ($checked) {
        $permissionMessage = $this->t('<strong>Notice</strong>: no roles are currently granted permissions for this type.');
        $needsPermissions = [
          Permissions::viewScheduledTransitionsPermission($entityTypeId, $bundle),
          Permissions::addScheduledTransitionsPermission($entityTypeId, $bundle),
        ];
        // Find at least one role with at least one of the permissions.
        foreach ($roles as $role) {
          if (\count(\array_intersect($needsPermissions, $role->getPermissions())) > 0) {
            $permissionMessage = '';
            break;
          }
        }
      }
      else {
        $permissionMessage = '';
      }

      return [
        ['data' => ['#markup' => $entityType?->getLabel() ?? '']],
        ['data' => ['#markup' => $bundleInfo[$bundle]['label'] ?? '']],
        ['data' => ['#markup' => $permissionMessage]],
      ];
    }, \array_combine($possibleBundles, $possibleBundles));

    // Collapse the details element if anything is enabled.
    $form['bundles']['#title'] = $this->t('Enabled types (@count)', [
      '@count' => \count($enabledBundles),
    ]);
    $form['bundles']['#open'] = \count($enabledBundles) === 0;
    $form['bundles']['enabled']['#default_value'] = \array_fill_keys($enabledBundles, TRUE);

    $settings = $this->config('scheduled_transitions.settings');

    $form['cron'] = [
      '#type' => 'details',
      '#title' => $this->t('Automation'),
      '#open' => TRUE,
    ];
    $form['cron']['cron_create_queue_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create scheduling jobs in cron'),
      '#description' => $this->t('If this setting is not enabled, items must be created using <code>drush scheduled-transitions:queue-jobs</code> command.'),
      '#default_value' => (bool) $settings->get('automation.cron_create_queue_items'),
    ];

    $form['processing'] = [
      '#type' => 'details',
      '#title' => $this->t('Processing'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['processing']['retain_processed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retain processed scheduled transitions'),
      '#default_value' => $settings->get('retain_processed.enabled'),
      '#description' => $this->t('Whether to keep scheduled transitions after they have been processed, or to delete scheduled transitions immediately after processing.'),
    ];
    $form['processing']['retain_processed_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Retain processed duration'),
      '#min' => -1,
      '#default_value' => $settings->get('retain_processed.duration'),
      '#description' => $this->t('Number of seconds after a scheduled transition has been processed before deleting it. Use <code>-1</code> to retain forever.'),
      '#field_suffix' => $this->t('seconds'),
    ];
    $form['processing']['link_template'] = [
      '#type' => 'select',
      '#title' => $this->t('Link to processed revision'),
      '#description' => $this->t('Select the link template.'),
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $settings->get('retain_processed.link_template'),
      '#options' => [
        'revision' => $this->t('Revision'),
      ],
    ];

    $form['entity_operations'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity operations'),
      '#open' => TRUE,
    ];
    $form['entity_operations']['mirror_operation_view'] = [
      '#type' => 'select',
      '#title' => 'Mirror view scheduled transitions',
      '#description' => $this->t('When attempting to <em>view scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $settings->get('mirror_operations.view scheduled transition'),
    ];
    $form['entity_operations']['mirror_operation_add'] = [
      '#type' => 'select',
      '#title' => 'Mirror add scheduled transitions',
      '#description' => $this->t('When attempting to <em>add scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $settings->get('mirror_operations.add scheduled transition'),
    ];
    $form['entity_operations']['mirror_operation_reschedule'] = [
      '#type' => 'select',
      '#title' => 'Mirror reschedule scheduled transitions',
      '#description' => $this->t('When attempting to <em>reschedule scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disable -'),
      '#default_value' => $settings->get('mirror_operations.reschedule scheduled transitions'),
    ];

    $form['messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Messages'),
      '#open' => TRUE,
    ];
    $form['message_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Revision log override'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_override'),
      '#description' => $this->t('Whether users are permitted to override revision log templates per scheduled transition.'),
    ];
    $form['message_transition_latest'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Latest'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_latest'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-revision-id] [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_historical'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Historical'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_historical'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-revision-id] [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_copy_latest_draft'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Copy latest draft'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_copy_latest_draft'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:latest-state] [scheduled-transitions:latest-revision-id]'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $settings = $this->config('scheduled_transitions.settings')
      ->set('mirror_operations.view scheduled transition', $form_state->getValue('mirror_operation_view'))
      ->set('mirror_operations.add scheduled transition', $form_state->getValue('mirror_operation_add'))
      ->set('mirror_operations.reschedule scheduled transitions', $form_state->getValue('mirror_operation_reschedule'))
      ->set('automation.cron_create_queue_items', (bool) $form_state->getValue('cron_create_queue_items'))
      ->set('message_override', $form_state->getValue('message_override'))
      ->set('message_transition_latest', $form_state->getValue('message_transition_latest'))
      ->set('message_transition_historical', $form_state->getValue('message_transition_historical'))
      ->set('message_transition_copy_latest_draft', $form_state->getValue('message_transition_copy_latest_draft'))
      ->set('retain_processed', [
        'enabled' => (bool) $form_state->getValue(['processing', 'retain_processed']),
        'duration' => (int) $form_state->getValue(['processing', 'retain_processed_duration']),
        'link_template' => (string) $form_state->getValue(['processing', 'link_template']),
      ]);

    $settings->clear('bundles');
    $bundles = \array_filter($form_state->getValue('enabled'), static fn (int|string $bundleChecked): bool => $bundleChecked !== 0);
    foreach (\array_keys($bundles) as $index => $enabledBundle) {
      [$entityTypeId, $bundle] = \explode(':', $enabledBundle);
      $settings->set('bundles.' . $index . '.entity_type', $entityTypeId);
      $settings->set('bundles.' . $index . '.bundle', $bundle);
    }

    $settings->save();
    $this->cacheTagInvalidator->invalidateTags([static::SETTINGS_TAG]);
    parent::submitForm($form, $form_state);
  }

}
