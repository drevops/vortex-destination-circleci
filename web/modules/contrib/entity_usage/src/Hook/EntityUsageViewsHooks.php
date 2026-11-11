<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Hook;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for entity_usage.
 */
class EntityUsageViewsHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['entity_usage']['table']['group'] = $this->t('Entity Usage');
    $data['entity_usage']['count'] = [
      'title' => $this->t('Usage count'),
      'help' => $this->t('How many times the target entity is referenced by the source entity.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];
    return $data;
  }

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    // Provide a relationship for each entity type that has a base table.
    foreach ($this->entityTypeManager->getDefinitions() as $type => $entity_type) {
      $base_table = $entity_type->getBaseTable();
      if ($base_table === NULL || empty($data[$base_table]) || !$entity_type->hasKey('id') || !$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }
      // Decide what column to use as base field depending on this entity type
      // "id" type.
      $id_key = $entity_type->getKey('id');
      /** @var \Drupal\Core\Field\BaseFieldDefinition $id_field */
      $id_field = $this->entityFieldManager->getBaseFieldDefinitions($type)[$id_key];
      $target_id_column = $id_field->getType() === 'integer' ? 'target_id' : 'target_id_string';
      $data[$base_table][$type . '_to_usage_entity'] = [
        'title' => $this->t('Information about the usage of this @entity_type', [
          '@entity_type' => $entity_type->getLabel(),
        ]),
        'help' => $this->t('Creates a relationship about this <em>@entity_type</em> and the entity_usage information that relates to it.', [
          '@entity_type' => $entity_type->getLabel(),
        ]),
        'relationship' => [
          'base' => 'entity_usage',
          'base field' => $target_id_column,
          'field' => $entity_type->getKey('id'),
          'id' => 'standard',
          'label' => $this->t('Usage information (@entity_type)', [
            '@entity_type' => $entity_type->getLabel(),
          ]),
          'extra' => [
                    [
                      'field' => 'target_type',
                      'value' => $type,
                    ],
          ],
        ],
      ];
    }
  }

}
