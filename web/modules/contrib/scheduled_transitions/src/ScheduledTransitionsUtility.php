<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\scheduled_transitions\Exception\ScheduledTransitionMissingEntity;
use Drupal\scheduled_transitions\Form\ScheduledTransitionsSettingsForm as SettingsForm;

/**
 * Utilities for Scheduled Transitions module.
 */
class ScheduledTransitionsUtility implements ScheduledTransitionsUtilityInterface {

  /**
   * Query tag to alter target revisions.
   */
  public const QUERY_TAG_TARGET_REVISIONS = 'scheduled_transitions_target_revisions';

  /**
   * Cache bin ID for enabled bundled cache.
   */
  protected const CID_SCHEDULED_TRANSITIONS_BUNDLES = 'scheduled_transitions_enabled_bundles';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected CacheBackendInterface $cache,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected ModerationInformationInterface $moderationInformation,
    protected Token $token,
    protected TranslationInterface $stringTranslation,
    protected ScheduledTransitionsTokenReplacementFactoryInterface|null $tokenReplacementFactory,
  ) {
    if ($this->tokenReplacementFactory === NULL) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $this->tokenReplacementFactory = \Drupal::service(ScheduledTransitionsTokenReplacementFactory::class);
      // @codingStandardsIgnoreLine
      @\trigger_error('Calling ' . __METHOD__ . '() without the $tokenReplacementFactory argument is deprecated in scheduled_transitions:2.8.0 and will be required in scheduled_transitions:3.0.0. See https://www.drupal.org/project/scheduled_transitions/issues/3008841', E_USER_DEPRECATED);
    }
  }

  public function getTransitions(EntityInterface $entity): array {
    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    $ids = $transitionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity__target_type', $entity->getEntityTypeId())
      ->condition('entity__target_id', $entity->id())
      ->execute();
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface[] */
    return $transitionStorage->loadMultiple($ids);
  }

  public function getApplicableBundles(): array {
    $bundles = [];

    $bundleInfo = $this->bundleInfo->getAllBundleInfo();
    foreach ($bundleInfo as $entityTypeId => $entityTypeBundles) {
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $entityTypeBundles = \array_filter(
        $entityTypeBundles,
        fn ($bundleId): bool => $this->moderationInformation->shouldModerateEntitiesOfBundle($entityType, $bundleId),
        \ARRAY_FILTER_USE_KEY,
      );
      $bundles[$entityTypeId] = \array_keys($entityTypeBundles);
    }

    return \array_filter($bundles);
  }

  public function getBundles(): array {
    $enabledBundlesCache = $this->cache->get(static::CID_SCHEDULED_TRANSITIONS_BUNDLES);
    if ($enabledBundlesCache !== FALSE) {
      return $enabledBundlesCache->data ?? [];
    }

    $enabledBundles = $this->configFactory->get('scheduled_transitions.settings')
      ->get('bundles');
    $enabledBundles = \array_map(
      static fn (array $bundleConfig) => \sprintf('%s:%s', $bundleConfig['entity_type'], $bundleConfig['bundle']),
      \is_array($enabledBundles) ? $enabledBundles : [],
    );

    $applicableBundles = $this->getApplicableBundles();
    foreach ($applicableBundles as $entityTypeId => &$bundles) {
      $bundles = \array_filter(
        $bundles,
        static fn (string $bundle) => \in_array(\sprintf('%s:%s', $entityTypeId, $bundle), $enabledBundles, TRUE),
      );
    }

    // Remove entity types with no bundles enabled.
    $applicableBundles = \array_filter($applicableBundles, static fn (array $bundles): bool => \count($bundles) !== 0);
    $this->cache->set(static::CID_SCHEDULED_TRANSITIONS_BUNDLES, $applicableBundles, Cache::PERMANENT, [SettingsForm::SETTINGS_TAG]);
    return $applicableBundles;
  }

  public function getTargetRevisionIds(EntityInterface $entity, string $language): array {
    $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    [$idK, $revisionK, $langcodeK] = self::entityTypeKeys($entityStorage->getEntityType());

    $ids = $entityStorage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($idK, $entity->id())
      ->condition($langcodeK, $language)
      ->sort($revisionK, 'DESC')
      ->addTag(static::QUERY_TAG_TARGET_REVISIONS)
      ->execute();

    return \array_keys($ids);
  }

  public function generateRevisionLog(ScheduledTransitionInterface $scheduledTransition, RevisionLogInterface $newRevision): string {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($newRevision->getEntityTypeId());
    $latestRevisionId = $entityStorage->getLatestRevisionId($newRevision->id());
    $latest = NULL;
    if ($latestRevisionId !== NULL) {
      /** @var \Drupal\Core\Entity\RevisionableInterface $latest */
      // loadRevision should accept string|int.
      $latest = $entityStorage->loadRevision($latestRevisionId);
    }

    $latest ?? throw new ScheduledTransitionMissingEntity('Could not determine latest revision.');

    $options = $scheduledTransition->getOptions();
    if (($options['revision_log_override'] ?? NULL) === TRUE) {
      $template = $options['revision_log'] ?? '';
    }
    else {
      $newIsLatest = $newRevision->getRevisionId() === $latest->getRevisionId();
      $settings = $this->configFactory->get('scheduled_transitions.settings');
      $template = $newIsLatest
        ? $settings->get('message_transition_latest')
        : $settings->get('message_transition_historical');
    }

    $replacements = $this->tokenReplacementFactory->create($scheduledTransition, $newRevision, $latest);
    $replacements->setStringTranslation($this->stringTranslation);
    return $this->tokenReplace($template, $replacements);
  }

  /**
   * Creates a cache tag for scheduled transitions related to an entity.
   */
  public static function createScheduledTransitionsCacheTag(EntityInterface $entity): string {
    return \sprintf('scheduled_transitions_for:%s:%s', $entity->getEntityTypeId(), $entity->id());
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

  /**
   * Normalizes missing keys to NULL so they may be used with NULL operators.
   *
   * @phpstan-return array{string|null, string|null, string|null}
   */
  private static function entityTypeKeys(EntityTypeInterface $entityType): array {
    return [
      // @phpstan-ignore-next-line ternary.shortNotAllowed
      $entityType->getKey('id') ?: NULL,
      // @phpstan-ignore-next-line ternary.shortNotAllowed
      $entityType->getKey('revision') ?: NULL,
      // @phpstan-ignore-next-line ternary.shortNotAllowed
      $entityType->getKey('langcode') ?: NULL,
    ];
  }

}
