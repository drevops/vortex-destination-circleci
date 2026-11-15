<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent;
use Drupal\scheduled_transitions\Exception\ScheduledTransitionMissingEntity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Executes transitions.
 */
class ScheduledTransitionsRunner implements ScheduledTransitionsRunnerInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  use StringTranslationTrait;

  protected const LOCK_DURATION = 1800;

  /**
   * @internal
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected ModerationInformationInterface $moderationInformation,
    protected Token $token,
    protected EventDispatcherInterface $eventDispatcher,
    protected ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility,
    protected ScheduledTransitionsConfigInterface|null $scheduledTransitionsConfig = NULL,
    private ScheduledTransitionsTokenReplacementFactoryInterface|null $tokenReplacementFactory = NULL,
  ) {
    if ($this->scheduledTransitionsConfig === NULL) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $this->scheduledTransitionsConfig = \Drupal::service(ScheduledTransitionsConfigInterface::class);
      // @codingStandardsIgnoreLine
      @\trigger_error('Calling ' . __METHOD__ . '() without the $scheduledTransitionsConfig argument is deprecated in scheduled_transitions:2.4.0 and will be required in scheduled_transitions:3.0.0. See https://www.drupal.org/project/scheduled_transitions/issues/3008841', E_USER_DEPRECATED);
    }
    if ($this->tokenReplacementFactory === NULL) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $this->tokenReplacementFactory = \Drupal::service(ScheduledTransitionsTokenReplacementFactoryInterface::class);
      // @codingStandardsIgnoreLine
      @\trigger_error('Calling ' . __METHOD__ . '() without the $tokenReplacementFactory argument is deprecated in scheduled_transitions:2.8.0 and will be required in scheduled_transitions:3.0.0. See https://www.drupal.org/project/scheduled_transitions/issues/3008841', E_USER_DEPRECATED);
    }
  }

  public function runTransition(ScheduledTransitionInterface $scheduledTransition): void {
    $scheduledTransitionId = $scheduledTransition->id();
    $targs = [
      '@id' => $scheduledTransitionId,
    ];

    $entity = $scheduledTransition->getEntity();
    if (NULL === $entity) {
      $this->logger->info('Entity does not exist for scheduled transition #@id', $targs);
      throw new ScheduledTransitionMissingEntity(\sprintf('Entity does not exist for scheduled transition #%s', $scheduledTransitionId));
    }

    $event = new ScheduledTransitionsNewRevisionEvent($scheduledTransition);
    $this->eventDispatcher->dispatch($event, ScheduledTransitionsEvents::NEW_REVISION);

    $newRevision = $event->getNewRevision();
    $newRevision ?? throw new ScheduledTransitionMissingEntity(\sprintf('No revision could be determined to transition to for scheduled transition #%s', $scheduledTransitionId));

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    $latestRevisionId = $entityStorage->getLatestRevisionId($entity->id());
    if ($latestRevisionId !== NULL) {
      $latest = $entityStorage->loadRevision($latestRevisionId);
    }
    if (!isset($latest)) {
      $this->logger->info('Latest revision does not exist for scheduled transition #@id', $targs);
      throw new ScheduledTransitionMissingEntity(\sprintf('Latest revision does not exist for scheduled transition #%s', $scheduledTransitionId));
    }

    $originalRevisionIds = [$newRevision->getRevisionId(), $latest->getRevisionId()];
    $changedRevisionIds = $this->transitionEntity($scheduledTransition, $newRevision, $latest);
    $this->logger->info('Processed scheduled transition #@id', $targs);

    // Save process state to entity even if retaining to give hooks an
    // opportunity to react.
    $now = new \DateTimeImmutable('@' . $this->time->getRequestTime());
    $changedRevisionIds = \array_unique(\array_diff($changedRevisionIds, $originalRevisionIds));
    $scheduledTransition
      ->setIsProcessed($now, $changedRevisionIds)
      ->save();

    if (FALSE === $this->scheduledTransitionsConfig->isRetainingAfterProcessing($scheduledTransition)) {
      $this->logger->info('Deleted scheduled transition #@id', $targs);
      $scheduledTransition->delete();
    }
  }

  /**
   * Transition a revision.
   *
   * This method is responsible for saving new revision, and any intermediate
   * revisions if applicable.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition entity.
   * @param \Drupal\Core\Entity\EntityInterface $newRevision
   *   A new default revision.
   * @param \Drupal\Core\Entity\EntityInterface $latest
   *   The latest current revision.
   *
   * @return array<string|int>
   *   Changed revisions
   *
   * @internal Internals may change at any time.
   */
  private function transitionEntity(ScheduledTransitionInterface $scheduledTransition, EntityInterface $newRevision, EntityInterface $latest): array {
    $changedRevisionIds = [];

    /** @var \Drupal\Core\Entity\RevisionableInterface $newRevision */
    /** @var \Drupal\Core\Entity\RevisionableInterface $latest */
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($newRevision->getEntityTypeId());

    // Check this now before any new saves.
    // Remove if Scheduled Transitions supports non CM workflows in the future.
    $isLatestRevisionPublished = NULL;
    /** @var \Drupal\workflows\StateInterface|null $originalLatestState */
    $originalLatestState = NULL;
    $newState = NULL;
    if ($latest instanceof ContentEntityInterface) {
      $isLatestRevisionPublished = $this->moderationInformation->isLiveRevision($latest);

      $workflow = $this->moderationInformation->getWorkflowForEntity($latest);
      $workflowPlugin = $workflow->getTypePlugin();
      $states = $workflowPlugin->getStates();
      // @phpstan-ignore-next-line property.notFound
      $originalLatestState = $states[$latest->moderation_state->value ?? ''] ?? NULL;
      $newState = $states[$scheduledTransition->getState()] ?? NULL;
    }

    $replacements = $this->tokenReplacementFactory->create($scheduledTransition, $newRevision, $latest);

    // Start the transition process.
    // Determine if latest before calling setNewRevision on $newRevision.
    $newIsLatest = $newRevision->getRevisionId() === $latest->getRevisionId();
    /** @var string|null $revisionLog */
    $revisionLog = $newRevision instanceof RevisionLogInterface
      ? $this->scheduledTransitionsUtility->generateRevisionLog($scheduledTransition, $newRevision)
      : NULL;

    // When a revision log is an empty string, change it to NULL for checks below.
    $revisionLog = $revisionLog === '' ? NULL : $revisionLog;

    // Creating revisions via createRevision to invoke
    // setRevisionTranslationAffected and whatever other logic doesn't happen
    // automatically by simply setting setNewRevision on its own.
    // 'default' param: will be changed by content moderation anyway, and
    // ->setNewRevision() is called.
    $newRevision = $entityStorage->createRevision($newRevision, FALSE);
    $newRevision->moderation_state = $newState->id();

    if ($newRevision instanceof EntityChangedInterface) {
      $newRevision->setChangedTime($this->time->getRequestTime());
    }

    if ($newRevision instanceof RevisionLogInterface) {
      $newRevision->setRevisionUser($scheduledTransition->getAuthor());
    }

    // If publishing the latest revision, then only set moderation state.
    if ($newIsLatest) {
      $this->log(LogLevel::INFO, 'Transitioning latest revision #@original_revision_id from @original_state to @new_state', $replacements);
      if ($newRevision instanceof RevisionLogInterface && $revisionLog !== NULL) {
        $newRevision
          ->setRevisionLogMessage($revisionLog)
          ->setRevisionCreationTime($this->time->getRequestTime());
      }
      $newRevision->save();
      $changedRevisionIds[] = $newRevision->getRevisionId();
    }
    // Otherwise if publishing a revision not on HEAD, create new revisions.
    else {
      $this->log(LogLevel::INFO, 'Copied revision #@revision_id and changed from @original_state to @new_state', $replacements);
      if ($newRevision instanceof RevisionLogInterface && $revisionLog !== NULL) {
        $newRevision
          ->setRevisionLogMessage($revisionLog)
          ->setRevisionCreationTime($this->time->getRequestTime());
      }
      $newRevision->save();
      $changedRevisionIds[] = $newRevision->getRevisionId();

      $options = $scheduledTransition->getOptions();
      // If the new revision is now a default, and the old latest was not a
      // default (e.g Draft), then pull it back on top.
      // Value is not important, normally set to TRUE.
      if (\array_key_exists(ScheduledTransition::OPTION_RECREATE_NON_DEFAULT_HEAD, $options)) {
        // To republish, this revision cannot be published, and the state for
        // this revision must still exist.
        if (!$isLatestRevisionPublished && $originalLatestState !== NULL) {
          $latest = $entityStorage->createRevision($latest, FALSE);
          $this->log(LogLevel::INFO, 'Reverted @original_latest_state revision #@original_revision_id back to top', $replacements);
          if ($latest instanceof RevisionLogInterface) {
            $template = $this->scheduledTransitionsConfig->getMessageTransitionCopyLatestDraft($latest);
            $latest
              ->setRevisionLogMessage($this->tokenReplace($template, $replacements))
              ->setRevisionCreationTime($this->time->getRequestTime());
          }
          $latest->save();
          $changedRevisionIds[] = $latest->getRevisionId();
        }
      }
    }

    return $changedRevisionIds;
  }

  /**
   * Logs a message and adds context.
   *
   * @param mixed $level
   *   Log level.
   * @param string $message
   *   A log message.
   * @param \Drupal\scheduled_transitions\ScheduledTransitionsTokenReplacements $replacements
   *   A replacements object.
   */
  protected function log($level, string $message, ScheduledTransitionsTokenReplacements $replacements): void {
    $variables = $replacements->getReplacements();
    $targs = [
      '@new_state' => $variables['to-state'],
      '@original_state' => $variables['from-state'],
      '@revision_id' => $variables['from-revision-id'],
      '@original_latest_state' => $variables['latest-state'],
      '@original_revision_id' => $variables['latest-revision-id'],
    ];
    $this->logger->log($level, $message, $targs);
  }

  /**
   * Replaces all tokens in a given string with appropriate values.
   *
   * @param string $text
   *   A string containing replaceable tokens.
   * @param \Drupal\scheduled_transitions\ScheduledTransitionsTokenReplacements $replacements
   *   A replacements object.
   *
   * @return string
   *   The string with the tokens replaced.
   */
  protected function tokenReplace(string $text, ScheduledTransitionsTokenReplacements $replacements): string {
    $tokenData = ['scheduled-transitions' => $replacements->getReplacements()];
    return $this->token->replace($text, $tokenData);
  }

}
