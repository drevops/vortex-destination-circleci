<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsUtility;
use Drupal\user\UserInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Scheduled Transition entity.
 *
 * @ContentEntityType(
 *   id = "scheduled_transition",
 *   label = @Translation("scheduled transition"),
 *   label_singular = @Translation("Scheduled transition"),
 *   label_plural = @Translation("scheduled transitions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count scheduled transition",
 *     plural = "@count scheduled transitions"
 *   ),
 *   label_collection = @Translation("Scheduled transitions"),
 *   base_table = "scheduled_transition",
 *   data_table = "scheduled_transition",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "author",
 *   },
 *   internal = TRUE,
 *   admin_permission = "view all scheduled transitions",
 *   handlers = {
 *     "access" = \Drupal\scheduled_transitions\ScheduledTransitionsAccessControlHandler::class,
 *     "list_builder" = \Drupal\scheduled_transitions\ScheduledTransitionsListBuilder::class,
 *     "views_data" = \Drupal\scheduled_transitions\ScheduledTransitionsViewsData::class,
 *     "form" = {
 *       "delete" = \Drupal\Core\Entity\ContentEntityDeleteForm::class,
 *       "reschedule" = \Drupal\scheduled_transitions\Form\Entity\ScheduledTransitionRescheduleForm::class,
 *     },
 *     "route_provider" = {
 *       "html" = \Drupal\scheduled_transitions\Routing\ScheduledTransitionRouteProvider::class,
 *     }
 *   },
 *   links = {
 *     "collection" = "/admin/content/scheduled-transitions",
 *     "delete-form" = "/admin/scheduled-transition/{scheduled_transition}/delete",
 *     "reschedule-form" = "/admin/scheduled-transition/{scheduled_transition}/reschedule",
 *   },
 * )
 *
 * @property \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceFieldItemList $entity
 * @property \Drupal\Core\Field\FieldItemList $entity_revision_id
 * @property \Drupal\Core\Field\FieldItemList $entity_revision_langcode
 * @property \Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\user\UserInterface> $author
 * @property \Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\workflows\WorkflowInterface> $workflow
 * @property \Drupal\content_moderation\Plugin\Field\ModerationStateFieldItemList $moderation_state
 * @property \Drupal\Core\Field\FieldItemList $created
 * @property \Drupal\Core\Field\FieldItemList $transition_on
 * @property \Drupal\Core\Field\FieldItemList $locked_on
 * @property \Drupal\Core\Field\MapFieldItemList $options
 * @property \Drupal\Core\Field\FieldItemList $is_processed
 * @property \Drupal\datetime\Plugin\Field\FieldType\DateTimeFieldItemList $processed_date
 * @property \Drupal\Core\Field\FieldItemList $processed_revisions
 */
class ScheduledTransition extends ContentEntityBase implements ScheduledTransitionInterface {

  /**
   * Option to recreate non default head.
   */
  public const OPTION_RECREATE_NON_DEFAULT_HEAD = 'recreate_non_default_head';

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(\t('Entity'))
      ->setDescription(\t('The entity this scheduled transition is for.'))
      ->setRequired(TRUE)
      ->setCardinality(1);

    // Only supports entities with integer IDs.
    $fields['entity_revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(\t('Content entity revision ID'))
      ->setDescription(\t('The revision ID of the entity this scheduled transition is for.'))
      ->setRequired(TRUE)
      ->setCardinality(1);

    $fields['entity_revision_langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(\t('Content entity revision language'))
      ->setDescription(\t('The revision language of the entity this scheduled transition is for.'))
      ->setRequired(FALSE)
      ->setCardinality(1);

    $fields['author'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(\t('Authored by'))
      ->setDescription(\t('The user who initiated the scheduled transition.'))
      ->setSetting('target_type', 'user');

    // Workflow is recorded so scheduled transitions can be cleaned up if they
    // are deleted. Similar to 'bundle' column on field tables.
    $fields['workflow'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(\t('Workflow'))
      ->setDescription(\t('The workflow of the state.'))
      ->setSetting('target_type', 'workflow')
      ->setRequired(TRUE);

    $fields['moderation_state'] = BaseFieldDefinition::create('string')
      ->setLabel(\t('Moderation state'))
      ->setDescription(\t('The new state of the content.'))
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(\t('Authored on'))
      ->setDescription(\t('The time that the scheduled transition was created.'));

    $fields['transition_on'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(\t('Transition on'))
      ->setDescription(\t('The time scheduled transition should happen.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ]);

    $fields['locked_on'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(\t('Locked on'))
      ->setDescription(\t('The time a job was created to process the transition.'));

    $fields['is_processed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(\t('Is processed'))
      ->setDescription(\t('Whether the Scheduled Transition is processed.'))
      ->setRequired(TRUE)
      ->setDefaultValue(0)
      ->setInitialValue(0);

    $fields['processed_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(\t('Processed on'))
      ->setDescription(\t('The time the Scheduled Transition is processed.'));

    // Only supports entities with integer IDs.
    $fields['processed_revisions'] = BaseFieldDefinition::create('integer')
      ->setLabel(\t('Processed entity revision IDs'))
      ->setDescription(\t('The revision IDs of revisions saved.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['options'] = BaseFieldDefinition::create('map')
      ->setLabel(\t('Options'))
      ->setDescription(\t('Arbitrary settings for scheduled transition.'));

    return $fields;
  }

  public static function createFrom(WorkflowInterface $workflow, string $state, RevisionableInterface $revision, \DateTimeInterface $dateTime, AccountInterface $author): static {
    /** @var static $scheduledTransition */
    $scheduledTransition = static::create();
    $scheduledTransition
      ->setState($workflow, $state)
      ->setEntity($revision)
      ->setTransitionDate($dateTime)
      ->setAuthor($author);
    return $scheduledTransition;
  }

  public function getEntity(): ?EntityInterface {
    /** @var \Drupal\Core\Entity\EntityInterface|null */
    return $this->entity->entity;
  }

  /**
   * @phpstan-return $this
   */
  public function setEntity(RevisionableInterface $revision) {
    $this->set('entity', $revision);
    $this->setEntityRevisionId($revision->getRevisionId());
    $this->setEntityRevisionLanguage($revision->language()->getId());
    return $this;
  }

  public function getEntityRevisionId(): string|int|null {
    return $this->get('entity_revision_id')->value;
  }

  /**
   * @phpstan-return $this
   */
  public function setEntityRevisionId(string|int $revisionId) {
    return $this->set('entity_revision_id', $revisionId);
  }

  public function getEntityRevisionLanguage(): ?string {
    return $this->get('entity_revision_langcode')->value;
  }

  /**
   * @phpstan-return $this
   */
  public function setEntityRevisionLanguage(string $langCode) {
    // @phpstan-ignore-next-line
    $this->entity_revision_langcode = $langCode;
    return $this;
  }

  public function getAuthor(): ?UserInterface {
    return $this->author->entity;
  }

  /**
   * @phpstan-return $this
   */
  public function setAuthor(AccountInterface $author) {
    // @phpstan-ignore-next-line
    $this->author = $author->id();
    return $this;
  }

  public function getWorkflow(): ?WorkflowInterface {
    return $this->workflow->entity;
  }

  /**
   * @phpstan-return $this
   */
  public function setState(WorkflowInterface $workflow, string $state) {
    // @phpstan-ignore-next-line
    $this->workflow = $workflow->id();
    // @phpstan-ignore-next-line
    $this->moderation_state = $state;
    return $this;
  }

  public function getState(): ?string {
    return $this->get('moderation_state')->value;
  }

  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * @phpstan-return $this
   */
  public function setCreatedDate(\DateTimeInterface $createDate) {
    return $this->set('created', $createDate->getTimestamp());
  }

  public function getTransitionDate(): \DateTimeInterface {
    return new \DateTime('@' . $this->getTransitionTime());
  }

  public function getTransitionTime(): int {
    return (int) $this->get('transition_on')->value;
  }

  /**
   * @phpstan-return $this
   */
  public function setTransitionDate(\DateTimeInterface $dateTime) {
    $this->set('transition_on', $dateTime->getTimestamp());
    return $this;
  }

  /**
   * @phpstan-return $this
   */
  public function setTransitionTime(int $time) {
    $this->set('transition_on', $time);
    return $this;
  }

  /**
   * @phpstan-return $this
   */
  public function setLockedOn(int $time) {
    $this->set('locked_on', $time);
    return $this;
  }

  public function isProcessed(): bool {
    $value = (int) (string) $this->is_processed->value;
    return $value !== 0;
  }

  public function getProcessedDate(): ?\DateTimeInterface {
    /** @var string|int|null $value */
    $value = $this->processed_date->value;
    return $value !== NULL ? new \DateTimeImmutable('@' . $value) : NULL;
  }

  /**
   * @phpstan-return $this
   */
  public function setIsProcessed(\DateTimeInterface $transitionDate, array $revisionIds) {
    return $this
      ->set('is_processed', TRUE)
      ->set('processed_date', $transitionDate->getTimestamp())
      ->set('processed_revisions', $revisionIds);
  }

  /**
   * @phpstan-return $this
   */
  public function setIsNotProcessed() {
    $this->set('is_processed', FALSE);
    $this->set('processed_date', NULL);
    $this->set('processed_revisions', NULL);
    return $this;
  }

  public function getProcessedRevisions(): array {
    return \array_column($this->processed_revisions->getValue(), 'value');
  }

  public function getOptions(): array {
    $options = $this->get('options')->first();
    if (isset($options)) {
      return $options->getValue();
    }
    return [];
  }

  /**
   * @phpstan-return $this
   */
  public function setOptions(array $options) {
    // @phpstan-ignore-next-line
    $this->options = $options;
    return $this;
  }

  public function getCacheTagsToInvalidate(): array {
    $tags = parent::getCacheTagsToInvalidate();
    $entity = $this->getEntity();
    if ($entity !== NULL) {
      $tags[] = ScheduledTransitionsUtility::createScheduledTransitionsCacheTag($entity);
    }
    return $tags;
  }

  protected function invalidateTagsOnSave($update): void {
    parent::invalidateTagsOnSave($update);
    $entity = $this->getEntity();
    if ($entity !== NULL) {
      Cache::invalidateTags([ScheduledTransitionsUtility::createScheduledTransitionsCacheTag($entity)]);
    }
  }

}
