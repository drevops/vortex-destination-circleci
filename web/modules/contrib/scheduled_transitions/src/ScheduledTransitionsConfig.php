<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Scheduled Transitions configuration.
 */
class ScheduledTransitionsConfig implements ScheduledTransitionsConfigInterface {

  /**
   * @internal There is no extensibility promise for the constructor.
   */
  final public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  public function isCreatingQueueItemsInHookCron(): bool {
    return (bool) $this->configFactory
      ->get('scheduled_transitions.settings')
      ->get('automation.cron_create_queue_items');
  }

  public function isRetainingAfterProcessing(ScheduledTransitionInterface $scheduledTransition): bool {
    return (bool) $this->configFactory
      ->get('scheduled_transitions.settings')
      ->get('retain_processed.enabled');
  }

  public function isRetentionDurationForever(): bool {
    return $this->getRetentionDuration() === -1;
  }

  public function getRetentionDuration(): int {
    /** @var int<-1, max> */
    return (int) $this->configFactory
      ->get('scheduled_transitions.settings')
      ->get('retain_processed.duration');
  }

  public function getProcessedLinkTemplate(RevisionableInterface $entity): ?string {
    $linkTemplate = $this->configFactory
      ->get('scheduled_transitions.settings')
      ->get('retain_processed.link_template');
    return \is_string($linkTemplate) && $linkTemplate !== '' ? $linkTemplate : NULL;
  }

  public function getMessageTransitionCopyLatestDraft(RevisionLogInterface $revision): string {
    return (string) $this->configFactory
      ->get('scheduled_transitions.settings')
      ->get('message_transition_copy_latest_draft');
  }

}
