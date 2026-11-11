<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Represents strings used as replacement variables in translation or logger.
 *
 * @internal
 */
final class ScheduledTransitionsTokenReplacements {

  use StringTranslationTrait;

  protected ?array $cachedReplacements = NULL;

  /**
   * @internal
   */
  public function __construct(
    private ScheduledTransitionInterface $scheduledTransition,
    private RevisionableInterface $newRevision,
    private RevisionableInterface $latest,
    private ModerationInformationInterface $moderationInformation,
  ) {
  }

  /**
   * Get variables for translation or replacement.
   *
   * @return array
   *   An array of strings keyed by replacement key.
   */
  public function getReplacements(): array {
    if (isset($this->cachedReplacements)) {
      return $this->cachedReplacements;
    }

    $entityRevisionId = $this->newRevision->getRevisionId();

    // getWorkflowForEntity only supports Content Entities, this can be removed
    // if Scheduled Transitions supports non CM workflows in the future.
    /** @var \Drupal\workflows\StateInterface[] $states */
    $states = [];
    if ($this->latest instanceof ContentEntityInterface) {
      $workflow = $this->moderationInformation->getWorkflowForEntity($this->latest);
      $workflowPlugin = $workflow->getTypePlugin();
      $states = $workflowPlugin->getStates();
    }

    // @phpstan-ignore-next-line property.notFound
    $originalNewRevisionState = $states[$this->newRevision->moderation_state->value ?? ''] ?? NULL;
    // @phpstan-ignore-next-line property.notFound
    $originalLatestState = $states[$this->latest->moderation_state->value ?? ''] ?? NULL;
    $newState = $states[$this->scheduledTransition->getState()] ?? NULL;

    return $this->cachedReplacements = [
      'from-revision-id' => $entityRevisionId,
      'from-state' => $originalNewRevisionState?->label() ?? $this->t('- Unknown state -'),
      'to-state' => $newState?->label() ?? $this->t('- Unknown state -'),
      'latest-revision-id' => $this->latest->getRevisionId(),
      'latest-state' => $originalLatestState?->label() ?? $this->t('- Unknown state -'),
    ];
  }

}
