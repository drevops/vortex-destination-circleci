<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Decorates a cache backend to stop it caching entities during bulk loads.
 *
 * Bulk loading source entities also loads any entities referenced from them,
 * such as paragraphs. None of these are needed again once tracked, so
 * persistently caching them is pure overhead. This decorates whichever cache
 * backend a storage is currently using, rather than replacing it outright, so
 * that any other module's decoration of that backend (for example Trash's
 * filtering of deleted entities) is preserved for the delete/invalidate
 * operations that still reach it.
 *
 * @see \Drupal\entity_usage\EntityUsageBatchManager::disableStorageCacheBackend()
 */
class BulkLoadCacheBackendDecorator implements CacheBackendInterface, CacheTagsInvalidatorInterface {

  public function __construct(
    protected CacheBackendInterface $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $cids = [];
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
    // Do nothing: entities loaded during bulk processing are not cached.
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    // Do nothing: entities loaded during bulk processing are not cached.
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->inner->delete($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $this->inner->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->inner->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->inner->invalidate($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $this->inner->invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $this->inner->invalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $this->inner->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->inner->removeBin();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if ($this->inner instanceof CacheTagsInvalidatorInterface) {
      $this->inner->invalidateTags($tags);
    }
  }

}
