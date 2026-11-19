<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_usage\Attribute\EntityUsageTrack;
use Drupal\entity_usage\EntityUsageTrackBase;

/**
 * Tracks usage of entities related in Link fields.
 */
#[EntityUsageTrack(
  id: 'link',
  label: new TranslatableMarkup('Link Fields'),
  description: new TranslatableMarkup("Tracks relationships created with 'Link' fields."),
  field_types: ["link", "link_tree"],
)]
class Link extends EntityUsageTrackBase {

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    /** @var \Drupal\link\LinkItemInterface $item */
    if ($item->isExternal()) {
      $url = $item->getUrl()->toString();
      $entity_info = $this->urlToEntity->findEntityIdByUrl($url);
    }
    else {
      $url = $item->getUrl();
      if ($url->isRouted()) {
        $entity_info = $this->urlToEntity->findEntityIdByRoutedUrl($url);
      }
      else {
        $entity_info = $this->urlToEntity->findEntityIdByUrl($url->toString());
      }
    }

    if (empty($entity_info)) {
      return [];
    }

    ['type' => $entity_type_id, 'id' => $entity_id] = $entity_info;
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $query = $this->entityTypeManager->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition($entity_type->getKey('id'), $entity_id);
    return array_values(array_map(fn ($id) => $entity_type_id . '|' . $id, $query->execute()));
  }

}
