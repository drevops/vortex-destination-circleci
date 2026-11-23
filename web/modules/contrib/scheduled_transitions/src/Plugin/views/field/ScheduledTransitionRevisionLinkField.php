<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Link to the scheduled revision.
 *
 * @ViewsField(\Drupal\scheduled_transitions\Plugin\views\field\ScheduledTransitionRevisionLinkField::PLUGIN_ID)
 */
class ScheduledTransitionRevisionLinkField extends LinkBase {

  public const PLUGIN_ID = 'scheduled_transitions_revision_link';

  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccessManagerInterface $access_manager,
    EntityTypeManagerInterface $entityTypeManager,
    EntityRepositoryInterface $entityRepository,
    LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $access_manager, $entityTypeManager, $entityRepository, $languageManager);
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(AccessManagerInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(EntityRepositoryInterface::class),
      $container->get(LanguageManagerInterface::class),
    );
  }

  protected function checkUrlAccess(ResultRow $row): AccessResultInterface {
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition */
    $scheduledTransition = $this->getEntity($row);
    $entity = $scheduledTransition->getEntity();
    if (NULL === $entity || !$entity->getEntityType()->hasLinkTemplate('revision')) {
      return AccessResult::neutral('Entity does not have a revision/canonical template.');
    }
    return parent::checkUrlAccess($row);
  }

  protected function getUrlInfo(ResultRow $row): ?Url {
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition */
    $scheduledTransition = $this->getEntity($row);

    $entity = $scheduledTransition->getEntity();
    if ($entity === NULL) {
      return NULL;
    }
    $entityRevisionId = $scheduledTransition->getEntityRevisionId();

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $entityRevision = $entityStorage->loadRevision($entityRevisionId);

    if (NULL === $entityRevision) {
      // Use the original entity if this revision cannot be loaded.
      $entityRevision = $entity;
    }

    $language = $scheduledTransition->getEntityRevisionLanguage();
    if ($language !== NULL && $entityRevision instanceof TranslatableInterface && $entityRevision->hasTranslation($language)) {
      $entityRevision = $entityRevision->getTranslation($language);
    }

    $toUrlArgs = [];
    if ($entityRevision->hasLinkTemplate('revision')) {
      $toUrlArgs[] = 'revision';
    }
    return $entityRevision->toUrl(...$toUrlArgs);
  }

  // @phpstan-ignore-next-line method.childReturnType
  protected function renderLink(ResultRow $row): string|MarkupInterface {
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition */
    $scheduledTransition = $this->getEntity($row);

    $entity = $scheduledTransition->getEntity();
    if (NULL === $entity) {
      return '';
    }
    $entityRevisionId = $scheduledTransition->getEntityRevisionId();

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $entityRevision = $entityStorage->loadRevision($entityRevisionId);
    if ($entityRevision === NULL) {
      $options = $scheduledTransition->getOptions();
      // Drupal hints only `string` is allowed. It is wrong.
      return isset($options[ScheduledTransition::OPTION_LATEST_REVISION])
        ? $this->t('Latest revision')
        : $this->t('Dynamic');
    }
    $text = parent::renderLink($row);
    $this->options['alter']['query'] = $this->getDestinationArray();
    return $text;
  }

  protected function getDefaultLabel(): MarkupInterface {
    return $this->t('View revision');
  }

}
