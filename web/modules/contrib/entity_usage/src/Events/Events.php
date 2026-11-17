<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Events;

/**
 * Contains all events thrown by Entity Usage.
 */
final class Events {

  /**
   * Occurs when usage records are added or updated.
   */
  const string USAGE_REGISTER = 'entity_usage.register';

  /**
   * Occurs when all records of a given target entity type are removed.
   */
  const string BULK_DELETE_DESTINATIONS = 'entity_usage.bulk_delete_targets';

  /**
   * Occurs when all records of a given source entity type are removed.
   */
  const string BULK_DELETE_SOURCES = 'entity_usage.bulk_delete_sources';

  /**
   * Occurs when all records from a given entity_type + field are deleted.
   */
  const string DELETE_BY_FIELD = 'entity_usage.delete_by_field';

  /**
   * Occurs when all records from a given source entity are deleted.
   */
  const string DELETE_BY_SOURCE_ENTITY = 'entity_usage.delete_by_source_entity';

  /**
   * Occurs when all records from a given target entity are deleted.
   */
  const string DELETE_BY_TARGET_ENTITY = 'entity_usage.delete_by_target_entity';

  /**
   * Occurs when we need to convert a URL string to an entity.
   *
   * @see \Drupal\entity_usage\UrlToEntity
   * @see \Drupal\entity_usage\Events\UrlToEntityEvent
   */
  const string URL_TO_ENTITY = 'entity_usage.url_to_entity';

}
