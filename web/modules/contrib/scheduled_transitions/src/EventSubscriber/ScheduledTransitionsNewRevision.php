<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Determines which revision should be transitioned.
 */
class ScheduledTransitionsNewRevision implements EventSubscriberInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Default new revision functionality.
   *
   * Determines the new revision based on the revision ID on the scheduled
   * transition entity.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   *   New revision event.
   */
  public function newRevision(ScheduledTransitionsNewRevisionEvent $event): void {
    $scheduledTransition = $event->getScheduledTransition();
    $scheduledTransitionId = $scheduledTransition->id();
    $targs = [
      '@id' => $scheduledTransitionId,
    ];

    $entity = $scheduledTransition->getEntity();
    if (NULL === $entity) {
      $this->logger->info('Entity does not exist for scheduled transition #@id', $targs);
      return;
    }

    $entityRevisionId = $scheduledTransition->getEntityRevisionId();
    if (NULL === $entityRevisionId) {
      // Revision ID with zero value usually means revision is determined
      // dynamically with another subscriber.
      $this->logger->debug('New revision will be determined dynamically for scheduled transition #@id', $targs);
      return;
    }

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    $newRevision = $entityStorage->loadRevision($entityRevisionId);
    if (!isset($newRevision)) {
      $this->logger->info('Target revision does not exist for scheduled transition #@id', $targs);
      return;
    }

    $entityRevisionLanguage = $scheduledTransition->getEntityRevisionLanguage();
    if ($entityRevisionLanguage !== NULL && $newRevision instanceof TranslatableInterface && $newRevision->hasTranslation($entityRevisionLanguage)) {
      $newRevision = $newRevision->getTranslation($entityRevisionLanguage);
    }

    $event->setNewRevision($newRevision);
  }

  /**
   * Latest revision functionality.
   *
   * Automatically determines the latest revision, which will be the new saved
   * revision.
   */
  public function latestRevision(ScheduledTransitionsNewRevisionEvent $event): void {
    $scheduledTransition = $event->getScheduledTransition();

    $options = $scheduledTransition->getOptions();
    if (!isset($options[ScheduledTransition::OPTION_LATEST_REVISION])) {
      return;
    }

    // Load the latest revision.
    $entity = $scheduledTransition->getEntity();
    if (NULL !== $entity) {
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
      $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      $latestRevisionId = $entityStorage->getLatestRevisionId($entity->id());
      if ($latestRevisionId !== NULL) {
        /** @var \Drupal\Core\Entity\RevisionableInterface $newRevision */
        $newRevision = $entityStorage->loadRevision($latestRevisionId);
      }
    }

    if (!isset($newRevision)) {
      return;
    }

    // Get the correct language.
    $entityRevisionLanguage = $scheduledTransition->getEntityRevisionLanguage();
    if ($entityRevisionLanguage !== NULL && $newRevision instanceof TranslatableInterface && $newRevision->hasTranslation($entityRevisionLanguage)) {
      $newRevision = $newRevision->getTranslation($entityRevisionLanguage);
    }

    $this->logger->debug('Latest revision was loaded for for scheduled transition #@id', [
      '@id' => $scheduledTransition->id(),
    ]);
    $event->setNewRevision($newRevision);
    $event->stopPropagation();
  }

  public static function getSubscribedEvents(): array {
    // Latest revision has priority over default.
    $events = [];
    $events[ScheduledTransitionsEvents::NEW_REVISION][] = [
      'latestRevision',
      1000,
    ];
    $events[ScheduledTransitionsEvents::NEW_REVISION][] = ['newRevision'];
    return $events;
  }

}
