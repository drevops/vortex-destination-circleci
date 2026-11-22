<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;
use Drupal\entity_usage\Attribute\EntityUsageTrack;
use Drupal\entity_usage\EntityUsageInlineTrackingInterface;
use Drupal\entity_usage\EntityUsageTrackBase;
use Drupal\entity_usage\EntityUsageTrackManager;
use Drupal\entity_usage\EntityUsageTrackMultipleLoadInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tracks usage of entities referenced within entity reference revision fields.
 *
 * This plugin makes the referenced entities "inline" in the entity_usage table.
 * Instead of recording paragraphs as sources, it looks through all paragraph
 * fields (accounting for nesting) and records the parent non-paragraph entity
 * as the source.
 */
#[EntityUsageTrack(
  id: 'entity_reference_revision_field',
  label: new TranslatableMarkup('Entity reference revision field'),
  description: new TranslatableMarkup("Tracks relationships via 'Entity Reference Revisions' fields, for example via paragraphs."),
  field_types: [
    'entity_reference_revisions',
  ]
)]
class EntityReferenceRevisionField extends EntityUsageTrackBase implements EntityUsageInlineTrackingInterface {

  /**
   * The entity usage track plugin manager.
   */
  protected EntityUsageTrackManager $trackManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->trackManager = $container->get('plugin.manager.entity_usage.track');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getInlineEntityTypeIds(): array {
    return ['paragraph'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    assert($item instanceof EntityReferenceRevisionsItem);
    $paragraph_entity = $item->entity;
    if (!($paragraph_entity instanceof FieldableEntityInterface)) {
      return [];
    }
    $target_entities = [];
    foreach ($this->collectTargetsFromParagraphs($paragraph_entity) as $entry) {
      $target_entities[] = $entry['target_type'] . '|' . $entry['target_id'];
    }
    return array_unique($target_entities);
  }

  /**
   * {@inheritdoc}
   */
  public function trackOnEntityCreation(EntityInterface $source_entity): void {
    if (!($source_entity instanceof FieldableEntityInterface)) {
      return;
    }

    $trackable_field_types = $this->getApplicableFieldTypes();
    $fields = array_keys($this->getReferencingFields($source_entity, $trackable_field_types));
    $source_vid = ($source_entity instanceof RevisionableInterface && $source_entity->getRevisionId())
      ? (int) $source_entity->getRevisionId() : 0;

    foreach ($fields as $field_name) {
      if (!$source_entity->hasField($field_name) || $source_entity->{$field_name}->isEmpty()) {
        continue;
      }
      $targets = [];
      foreach ($source_entity->{$field_name} as $item) {
        $paragraph = $item->entity;
        if (!($paragraph instanceof FieldableEntityInterface)) {
          continue;
        }
        try {
          foreach ($this->collectTargetsFromParagraphs($paragraph) as $entry) {
            $targets[] = $entry['target_type'] . '|' . $entry['target_id'];
          }
        }
        catch (\Exception $e) {
          $this->logTrackingException($e, $source_entity, $field_name);
        }
      }
      foreach (array_unique($targets) as $target) {
        [$target_type, $target_id] = explode('|', $target);
        $this->usageService->registerUsage(
          $target_id,
          $target_type,
          $source_entity->id(),
          $source_entity->getEntityTypeId(),
          $source_entity->language()->getId(),
          $source_vid,
          $this->pluginId,
          $field_name
        );
      }
    }
  }

  /**
   * Update tracking data for an ERR field on an existing revision.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $source_entity
   *   The source entity.
   * @param string $field_name
   *   The top-level paragraph field name on the source entity.
   */
  public function updateTrackingDataForField(FieldableEntityInterface $source_entity, string $field_name): void {
    // Collect current targets from all paragraphs in this field.
    $current_targets = [];
    if ($source_entity->hasField($field_name) && !$source_entity->{$field_name}->isEmpty()) {
      foreach ($source_entity->{$field_name} as $item) {
        $paragraph = $item->entity;
        if ($paragraph instanceof FieldableEntityInterface) {
          try {
            $collected = $this->collectTargetsFromParagraphs($paragraph);
            foreach ($collected as $entry) {
              $current_targets[] = $entry['target_type'] . '|' . $entry['target_id'];
            }
          }
          catch (\Exception $e) {
            $this->logTrackingException($e, $source_entity, $field_name);
          }
        }
      }
    }

    $source_entity_langcode = $source_entity->language()->getId();
    $source_vid = ($source_entity instanceof RevisionableInterface && $source_entity->getRevisionId())
      ? (int) $source_entity->getRevisionId() : 0;
    $original_targets = $this->usageService->listTargetEntitiesByFieldAndMethod(
      $source_entity->id(),
      $source_entity->getEntityTypeId(),
      $source_entity_langcode,
      $source_vid,
      $this->pluginId,
      $field_name
    );

    $original_targets = array_unique($original_targets);
    $current_targets = array_unique($current_targets);

    $added_ids = array_diff($current_targets, $original_targets);
    $removed_ids = array_diff($original_targets, $current_targets);

    foreach ($added_ids as $added_entity) {
      [$target_type, $target_id] = explode('|', $added_entity);
      $this->usageService->registerUsage(
        $target_id, $target_type,
        $source_entity->id(), $source_entity->getEntityTypeId(),
        $source_entity_langcode, $source_vid,
        $this->pluginId, $field_name
      );
    }
    foreach ($removed_ids as $removed_entity) {
      [$target_type, $target_id] = explode('|', $removed_entity);
      $this->usageService->registerUsage(
        $target_id, $target_type,
        $source_entity->id(), $source_entity->getEntityTypeId(),
        $source_entity_langcode, $source_vid,
        $this->pluginId, $field_name, 0
      );
    }
  }

  /**
   * Recursively collect target entities from within a paragraph.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $paragraph_entity
   *   The paragraph entity to examine.
   *
   * @return array<array{target_type: string, target_id: string|int}>
   *   Array of collected targets.
   */
  protected function collectTargetsFromParagraphs(FieldableEntityInterface $paragraph_entity): array {
    $results = [];

    // Collect targets from all non-entity reference revision fields using other
    // plugins.
    foreach ($this->getEnabledPlugins() as $plugin) {
      $trackable_field_types = $plugin->getApplicableFieldTypes();
      $fields = array_keys($plugin->getReferencingFields($paragraph_entity, $trackable_field_types));

      foreach ($fields as $field_name) {
        if (!$paragraph_entity->hasField($field_name) || $paragraph_entity->{$field_name}->isEmpty()) {
          continue;
        }
        try {
          if ($plugin instanceof EntityUsageTrackMultipleLoadInterface) {
            $targets = $plugin->getTargetEntitiesFromField($paragraph_entity->{$field_name});
          }
          else {
            $targets = [];
            foreach ($paragraph_entity->{$field_name} as $field_item) {
              $targets = array_merge($targets, $plugin->getTargetEntities($field_item));
            }
          }
        }
        catch (\Exception $e) {
          $this->logTrackingException($e, $paragraph_entity, $field_name);
          continue;
        }

        foreach (array_unique($targets) as $target) {
          [$target_type, $target_id] = explode('|', $target);
          $results[] = [
            'target_type' => $target_type,
            'target_id' => $target_id,
          ];
        }
      }
    }

    // Recurse into nested paragraph references.
    $err_fields = $this->getReferencingFields($paragraph_entity, $this->getApplicableFieldTypes());
    foreach (array_keys($err_fields) as $nested_field_name) {
      if (!$paragraph_entity->hasField($nested_field_name) || $paragraph_entity->{$nested_field_name}->isEmpty()) {
        continue;
      }
      foreach ($paragraph_entity->{$nested_field_name} as $item) {
        $nested = $item->entity;
        if ($nested instanceof FieldableEntityInterface) {
          $results = array_merge($results, $this->collectTargetsFromParagraphs($nested));
        }
      }
    }

    return $results;
  }

  /**
   * Gets the enabled tracking plugins, excluding this plugin.
   *
   * @return array<string, \Drupal\entity_usage\EntityUsageTrackInterface>
   *   The enabled plugin instances keyed by plugin ID.
   */
  protected function getEnabledPlugins(): array {
    // Exclude this plugin to prevent infinite recursion.
    return $this->trackManager->getEnabledPlugins($this->config->get('track_enabled_plugins'), $this->pluginId);
  }

  /**
   * Logs a tracking exception.
   *
   * @param \Exception $e
   *   The exception to log.
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity that caused the exception.
   * @param string $field_name
   *   The field name that caused the exception.
   */
  private function logTrackingException(\Exception $e, EntityInterface $source_entity, string $field_name): void {
    Error::logException(
      $this->entityUsageLogger,
      $e,
      'Calculating entity usage for field %field on @entity_type:@entity_id using the %plugin plugin threw %type: @message in %function (line %line of %file).',
      [
        '%plugin' => $this->getPluginId(),
        '@entity_type' => $source_entity->getEntityTypeId(),
        '@entity_id' => $source_entity->id(),
        '%field' => $field_name,
      ]
    );
  }

}
