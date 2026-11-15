<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Scheduled transition list builder.
 */
class ScheduledTransitionsListBuilder extends EntityListBuilder {

  final public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get(EntityTypeManagerInterface::class)->getStorage($entity_type->id()),
      $container->get(DateFormatterInterface::class),
    );
  }

  public function load(): array {
    $query = $this->storage->getQuery();
    $query->accessCheck(TRUE);
    $header = $this->buildHeader();
    $query->tableSort($header);
    $ids = $query->execute();
    return $this->storage->loadMultiple($ids);
  }

  public function buildHeader(): array {
    $header = [
      'entity' => $this->t('Entity'),
      'date' => [
        'data' => $this->t('On date'),
        'field' => 'transition_on',
        'specifier' => 'transition_on',
        'sort' => 'asc',
      ],
    ] + parent::buildHeader();
    return $header;
  }

  public function buildRow(EntityInterface $entity): array {
    \assert($entity instanceof ScheduledTransitionInterface);
    $row = [];

    $hostEntity = $entity->getEntity();
    try {
      $row['host_entity'] = $hostEntity?->toLink() ?? $this->t('- Missing entity -');
    }
    catch (UndefinedLinkTemplateException) {
      $row['host_entity'] = $hostEntity->label();
    }

    // Date.
    $time = $entity->getTransitionTime();
    $row['date'] = $this->dateFormatter->format($time);

    return $row + parent::buildRow($entity);
  }

  protected function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    $rescheduleUrl = $entity->toUrl('reschedule-form');
    // @todo improve access cacheability after
    // https://www.drupal.org/project/drupal/issues/3106517 +
    // https://www.drupal.org/project/drupal/issues/2473873 for now permissions
    // cache context is added manually in buildOperations.
    // Stan doesn't know about this type yet.
    if ($rescheduleUrl->access()) {
      $operations['reschedule'] = [
        'title' => $this->t('Reschedule'),
        'weight' => 20,
        'url' => $this->ensureDestination($rescheduleUrl),
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 500,
          ]),
        ],
      ];
    }

    return $operations;
  }

  public function buildOperations(EntityInterface $entity): array {
    $build = parent::buildOperations($entity);

    // Add access cacheability, remove after @todo in getDefaultOperations is
    // completed.
    $build['#cache']['contexts'][] = 'user.permissions';

    return $build;
  }

}
