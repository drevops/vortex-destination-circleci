<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsConfigInterface;

/**
 * Show all scheduled transitions for an entity.
 */
class ScheduledTransitionForm extends ContentEntityForm {

  use AutowireTrait;

  /**
   * Table headings.
   */
  private array $tableHeadings;

  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    protected LanguageManagerInterface $languageManager,
    protected ScheduledTransitionsConfigInterface $scheduledTransitionsConfig,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  public function getBaseFormId(): ?string {
    return NULL;
  }

  public function form(array $form, FormStateInterface $form_state): array {
    $entity = $this->getEntity();

    $this->tableHeadings = [
      'from_revision' => [
        'data' => $this->t('From revision'),
      ],
      'from_state' => [
        'data' => $this->t('From state'),
      ],
      'to_state' => [
        'data' => $this->t('To state'),
      ],
      'date' => [
        'data' => $this->t('On date'),
        'field' => 'transition_on',
        'specifier' => 'transition_on',
        'sort' => 'asc',
      ],
      'author' => [
        'data' => $this->t('Scheduled by'),
      ],
      'processed' => [
        'data' => $this->t('Processed'),
        'field' => 'processed_date',
        'specifier' => 'processed_date',
        'sort' => 'desc',
      ],
      'operations' => [
        'data' => $this->t('Operations'),
      ],
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $this->tableHeadings,
      '#empty' => $this->t('There are no scheduled transitions for @entity', [
        '@entity' => $entity->label(),
      ]),
    ];

    $entityTypeId = $entity->getEntityTypeId();
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entityTypeId);

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $scheduledTransitionStorage */
    $scheduledTransitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    /** @var array<string|int> $ids */
    $ids = $this->getEntityQuery($scheduledTransitionStorage, $entity)->execute();

    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface[] $scheduledTransitions */
    $scheduledTransitions = $scheduledTransitionStorage->loadMultiple($ids);
    $form['table']['#rows'] = \array_map(
      function (ScheduledTransitionInterface $scheduledTransition) use ($entityStorage) {
        $row = [];

        $workflowPlugin = $scheduledTransition->getWorkflow()?->getTypePlugin();
        $workflowStates = $workflowPlugin?->getStates() ?? [];

        // From...
        $entityRevisionId = $scheduledTransition->getEntityRevisionId();
        // loadRevision should accept string|int.
        $entityRevision = $entityStorage->loadRevision($entityRevisionId);
        $revisionTArgs = ['@revision_id' => $entityRevisionId];
        if ($entityRevision !== NULL) {
          $toLinkArgs = [$this->t('#@revision_id', $revisionTArgs)];
          if ($entityRevision->hasLinkTemplate('revision')) {
            $toLinkArgs[] = 'revision';
          }
          $row['from_revision'] = $entityRevision->toLink(...$toLinkArgs);
          $fromState = $workflowStates[$entityRevision->moderation_state->value] ?? NULL;
          $row['from_state'] = $fromState?->label() ?? $this->t('- Missing from workflow/state -');
        }
        else {
          if (\is_numeric($entityRevisionId) && $entityRevisionId > 0) {
            $row['from_revision'] = [
              // Span 'from_revision', 'from_state'.
              'colspan' => 2,
              'data' => $this->t('Deleted revision #@revision_id', $revisionTArgs),
            ];
          }
          else {
            $options = $scheduledTransition->getOptions();
            $text = isset($options[ScheduledTransition::OPTION_LATEST_REVISION])
              ? $this->t('Latest revision')
              : $this->t('Dynamic');
            $row['from_revision'] = [
              'colspan' => 2,
              'data' => $text,
            ];
          }
        }

        // To.
        $toState = $workflowStates[$scheduledTransition->getState()] ?? NULL;
        $row['to_state'] = $toState?->label() ?? $this->t('- Missing to workflow/state -');

        // Date.
        $time = $scheduledTransition->getTransitionTime();
        $row['date'] = $this->dateFormatter->format($time);

        // Author.
        $author = $scheduledTransition->getAuthor();
        if ($author !== NULL) {
          $row['author']['data'] = $this->moduleHandler->moduleExists('user') ? [
            '#theme' => 'username',
            '#account' => $author,
          ] : $author->toLink();
        }
        else {
          $row['author']['data'] = $this->t('- Missing user -');
        }

        // Processed.
        $row['processed']['data'] = $scheduledTransition->isProcessed() === FALSE
          ? $this->t('Not processed')
          : $this->getIsProcessedCell($scheduledTransition, $entityStorage);

        // Operations.
        $operations = $this->entityTypeManager
          ->getListBuilder('scheduled_transition')
          ->getOperations($scheduledTransition);
        $row['operations']['data'] = [
          '#type' => 'operations',
          '#links' => $operations,
        ];

        return $row;
      },
      $scheduledTransitions,
    );

    return $form;
  }

  /**
   * Get the entity query.
   *
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $scheduledTransitionStorage
   *   Scheduled transition storage.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity in context.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function getEntityQuery(RevisionableStorageInterface $scheduledTransitionStorage, EntityInterface $entity): QueryInterface {
    return $scheduledTransitionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity__target_type', $entity->getEntityTypeId())
      ->condition('entity__target_id', $entity->id())
      ->condition('entity_revision_langcode', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId())
      ->tableSort($this->tableHeadings);
  }

  /**
   * Get the cell contents for processed scheduled transitions.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   The scheduled transition in context.
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage
   *   Host entity storage.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Cell contents.
   */
  private function getIsProcessedCell(ScheduledTransitionInterface $scheduledTransition, RevisionableStorageInterface $entityStorage): TranslatableMarkup {
    $processedDate = $scheduledTransition->getProcessedDate();
    $processedRevisionIds = $scheduledTransition->getProcessedRevisions();
    $firstProcessedRevisionId = \array_shift($processedRevisionIds);
    $processedEntityRevision = $firstProcessedRevisionId !== NULL
      // loadRevision should accept string|int.
      ? $entityStorage->loadRevision($firstProcessedRevisionId)
      : NULL;

    $tArgs = [
      '@processed_date' => $processedDate !== NULL ? $this->dateFormatter->format($processedDate->getTimestamp()) : '',
    ];
    $cell = $this->t('On @processed_date', $tArgs);
    if ($processedEntityRevision !== NULL) {
      $processedLinkTemplate = $this->scheduledTransitionsConfig->getProcessedLinkTemplate($processedEntityRevision);
      if ($processedLinkTemplate !== NULL && $processedEntityRevision->getEntityType()->hasLinkTemplate($processedLinkTemplate)) {
        $url = $processedEntityRevision->toUrl($processedLinkTemplate);
        // Stan doesn't know about this type yet.
        if ($url->access($this->currentUser())) {
          $tArgs[':processed_link'] = $url->toString();
          $cell = $this->t('On <a href=":processed_link">@processed_date</a>', $tArgs);
        }
      }
    }

    return $cell;
  }

  protected function actions(array $form, FormStateInterface $form_state): array {
    return [];
  }

  public function save(array $form, FormStateInterface $form_state): int {
    return \SAVED_UPDATED;
  }

}
