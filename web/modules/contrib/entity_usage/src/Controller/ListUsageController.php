<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Controller;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\entity_usage\Form\EntityUsageFilterForm;
use Drupal\entity_usage\SourceEntityStatus;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\trash\Trash;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for our pages.
 */
class ListUsageController extends ControllerBase {

  /**
   * Number of items per page to use when nothing was configured.
   */
  const int ITEMS_PER_PAGE_DEFAULT = 25;

  /**
   * HTML id of the container swapped by EntityUsageFilterForm's HTMX request.
   */
  const string HTMX_LIST_ID = 'entity-usage-list';

  /**
   * The index for the default revision "group".
   */
  protected const int REVISION_DEFAULT = 0;

  /**
   * The index for the pending revision "group".
   */
  protected const int REVISION_PENDING = 1;

  /**
   * The index for the old revision "group".
   */
  protected const int REVISION_OLD = -1;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The EntityUsage service.
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected $entityUsage;

  /**
   * The Entity Usage settings config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $entityUsageConfig;

  /**
   * The number of records per page this controller should output.
   *
   * @var int
   */
  protected $itemsPerPage;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The inline block usage service.
   *
   * @var \Drupal\layout_builder\InlineBlockUsageInterface|null
   */
  protected $inlineBlockUsage;

  /**
   * The trash manager.
   *
   * @var \Drupal\trash\TrashManagerInterface|null
   */
  protected ?TrashManagerInterface $trashManager;

  /**
   * ListUsageController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\entity_usage\EntityUsageInterface $entity_usage
   *   The EntityUsage service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   * @param \Drupal\layout_builder\InlineBlockUsageInterface|null $inline_block_usage
   *   The inline block usage.
   * @param \Drupal\trash\TrashManagerInterface|null $trash_manager
   *   The trash manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityUsageInterface $entity_usage,
    ConfigFactoryInterface $config_factory,
    PagerManagerInterface $pager_manager,
    ?InlineBlockUsageInterface $inline_block_usage,
    ?TrashManagerInterface $trash_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityUsage = $entity_usage;
    $this->entityUsageConfig = $config_factory->get('entity_usage.settings');
    $this->itemsPerPage = $this->entityUsageConfig->get('usage_controller_items_per_page') ?: self::ITEMS_PER_PAGE_DEFAULT;
    $this->pagerManager = $pager_manager;
    $this->inlineBlockUsage = $inline_block_usage;
    $this->trashManager = $trash_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_usage.usage'),
      $container->get('config.factory'),
      $container->get('pager.manager'),
      $container->get('inline_block.usage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get('trash.manager', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * Lists the usage of a given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int|string $entity_id
   *   The entity ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return mixed[]
   *   The page build to be rendered.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function listUsagePage(string $entity_type, int|string $entity_id, Request $request): array {
    // Old revisions are hidden by default; the checkbox in the filter form
    // below is how a user opts back in.
    $include_old_revisions = $request->query->getBoolean('list_old_revisions');

    // The filter checkbox is the only HTMX trigger on this page, so an HTMX
    // request here always means the filter just changed. Any "page" query
    // argument it carries is stale, and the HTMX request-negotiation
    // arguments core/misc/htmx/htmx-assets.js adds to every HTMX request
    // (see its "htmx:configRequest" handler) don't belong in rendered
    // output at all. Removing "_wrapper_format" here also means
    // \Drupal\Core\EventSubscriber\MainContentViewSubscriber (which runs after
    // this controller returns) falls back to rendering the *current* HTMX
    // response as a full HTML page rather than Drupal's lean "drupal_htmx"
    // wrapper; that's a harmless, if slightly wasteful, side effect, since
    // HTMX's "hx-select" on the filter checkbox (see EntityUsageFilterForm)
    // extracts the swapped container from whatever markup it receives either
    // way.
    // @todo Remove once https://www.drupal.org/project/drupal/issues/2504709 is
    //   fixed.
    if ($request->headers->has('HX-Request')) {
      foreach (['page', '_wrapper_format', 'ajax_page_state', '_triggering_element_name', '_triggering_element_value'] as $key) {
        $request->query->remove($key);
      }
    }

    $entries = $this->getDisplayableEntries($entity_type, $entity_id, $include_old_revisions);

    // Wrapped in a container with a stable id so EntityUsageFilterForm can
    // target and swap it via HTMX, when available, without a full page
    // reload.
    $build = [
      '#type' => 'container',
      '#attributes' => ['id' => static::HTMX_LIST_ID],
      // The rendered content depends on this query argument.
      '#cache' => ['contexts' => ['url.query_args:list_old_revisions']],
    ];

    // Always shown, even when $entries is empty: a user hiding old revisions
    // needs a way to discover and reveal usages that only exist there,
    // instead of being told there are none at all.
    $build['filter_form'] = $this->formBuilder()->getForm(EntityUsageFilterForm::class);

    if (empty($entries)) {
      $build['empty'] = [
        '#markup' => $this->t(
          'There are no recorded usages for entity of type: @type with id: @id',
          ['@type' => $entity_type, '@id' => $entity_id]
        ),
      ];
      return $build;
    }

    $header = [
      $this->t('Entity'),
      $this->t('Type'),
      $this->t('Language'),
      $this->t('Field name'),
      $this->t('Used in'),
    ];

    $total = count($entries);
    $pager = $this->pagerManager->createPager($total, $this->itemsPerPage);
    $page = $pager->getCurrentPage();
    $page_entries = array_slice($entries, $page * $this->itemsPerPage, $this->itemsPerPage);
    $cacheable_metadata = new CacheableMetadata();
    $has_operations = FALSE;
    $page_rows = $this->buildRows($page_entries, $request, $cacheable_metadata, $include_old_revisions, $has_operations);
    if ($has_operations) {
      $header[] = $this->t('Operations');
    }

    $build['table'] = [
      '#theme' => 'table',
      '#rows' => $page_rows,
      '#header' => $header,
    ];
    $cacheable_metadata->applyTo($build['table']);

    $build['pager'] = [
      '#type' => 'pager',
      '#route_name' => '<current>',
    ];

    return $build;
  }

  /**
   * Retrieve all displayable usage entries for this target entity.
   *
   * This only queries the raw usage records and runs lightweight
   * existence/field-value checks; it never fully loads a source entity. That
   * way the pager total and entry ordering are correct (they account for
   * every filtering condition that would otherwise only be discoverable after
   * a full load), without the cost of loading every source entity just to
   * show one page of results.
   *
   * @param string $entity_type
   *   The type of the target entity.
   * @param int|string $entity_id
   *   The ID of the target entity.
   * @param bool $include_old_revisions
   *   Whether entries whose only usage is in a non-default revision should be
   *   included.
   *
   * @return array<int, array{source_type: string, source_id: int|string, records: mixed[]}>
   *   An indexed array of usage entries that should be displayed as sources
   *   for this target entity.
   */
  protected function getDisplayableEntries(string $entity_type, int|string $entity_id, bool $include_old_revisions = TRUE): array {
    $entries = [];

    // Tell the Trash module not to hide entities that are trashed.
    if (!is_null($this->trashManager)) {
      $prev_trash_context = $this->trashManager->getTrashContext();
      $this->trashManager->setTrashContext('ignore');
    }
    try {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
      if ($entity) {
        foreach ($this->entityUsage->listSources($entity) as $source_type => $ids) {
          foreach ($ids as $source_id => $records) {
            $entries[] = [
              'source_type' => $source_type,
              'source_id' => $source_id,
              'records' => $records,
            ];
          }
        }
        $entries = $this->filterDisplayableEntries($entries, $include_old_revisions);
      }
    }
    finally {
      // Restore previous trash context if we changed it.
      if (isset($prev_trash_context)) {
        $this->trashManager->setTrashContext($prev_trash_context);
      }
    }

    return $entries;
  }

  /**
   * Filters out usage entries that would not produce a displayable row.
   *
   * This is the single place that decides whether a source entity should be
   * skipped (orphaned usage records, soft-deleted inline blocks). It runs as
   * batched existence/field-value queries instead of full entity loads, since
   * this may run over every usage record for the target entity (not just the
   * current page), and it is what determines the pager total. ::buildRows()
   * trusts its output and does not repeat any of these checks; any new
   * condition for skipping a source entity belongs here.
   *
   * @param array<int, array{source_type: string, source_id: int|string, records: mixed[]}> $entries
   *   The candidate usage entries.
   * @param bool $include_old_revisions
   *   Whether entries whose only usage is in a non-default revision should be
   *   included. When FALSE, an entry is only dropped for this reason if none
   *   of its records reference the source entity's default (or a newer,
   *   pending) revision; an entry used in both an old and the default
   *   revision is kept either way.
   *
   * @return array<int, array{source_type: string, source_id: int|string, records: mixed[]}>
   *   The entries whose source entity exists and would be displayed.
   */
  protected function filterDisplayableEntries(array $entries, bool $include_old_revisions = TRUE): array {
    if (empty($entries)) {
      return $entries;
    }

    $ids_by_type = [];
    foreach ($entries as $entry) {
      $ids_by_type[$entry['source_type']][] = $entry['source_id'];
    }

    // Determine which candidate IDs actually exist, without loading full
    // entities. This drops orphaned usage records, i.e. records whose source
    // entity has since been deleted but not yet cleaned up here.
    $existing_ids = [];
    $revisionable_types = [];
    foreach ($ids_by_type as $source_type => $ids) {
      $storage = $this->entityTypeManager->getStorage($source_type);
      $result = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($storage->getEntityType()->getKey('id'), $ids, 'IN')
        ->execute();
      // For revisionable entity types this is keyed by revision ID, not
      // entity ID (the entity ID is only guaranteed to be the value). Flip it
      // so entity ID membership can be checked with isset() regardless of
      // whether the entity type is revisionable. For revisionable types, the
      // flipped value is then each entity's default revision ID, which is
      // enough to classify every record for that entity as current/old below
      // (see ::entryHasCurrentRevisionRecord()) without loading it.
      $existing_ids[$source_type] = array_flip($result);
      $revisionable_types[$source_type] = $storage->getEntityType()->isRevisionable();
    }

    // Soft-deleted inline blocks are always block_content entities. Layout
    // Builder's cron hook will delete them eventually, but until then we
    // don't want them showing as a source unless they still have an
    // associated host entity. Determine this with field-value queries and a
    // lookup against the (small, dedicated) inline block usage table, rather
    // than loading each block_content entity.
    $hidden_inline_block_ids = [];
    if ($this->inlineBlockUsage && isset($existing_ids['block_content'])) {
      $block_storage = $this->entityTypeManager->getStorage('block_content');
      $non_reusable_ids = $block_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($block_storage->getEntityType()->getKey('id'), array_keys($existing_ids['block_content']), 'IN')
        ->condition('reusable', 0)
        ->execute();
      foreach ($non_reusable_ids as $id) {
        $blockUsageData = $this->inlineBlockUsage->getUsage((int) $id);
        if (!$blockUsageData || is_null($blockUsageData->layout_entity_id)) {
          $hidden_inline_block_ids[$id] = TRUE;
        }
      }
    }

    // Only needed to decide the filter above; skip it entirely when old
    // revisions are being included anyway.
    $latest_affected_vids = $include_old_revisions ? [] : $this->getLatestAffectedRevisionIds($entries, $revisionable_types);

    return array_values(array_filter($entries, function (array $entry) use ($existing_ids, $hidden_inline_block_ids, $include_old_revisions, $revisionable_types, $latest_affected_vids): bool {
      if (!isset($existing_ids[$entry['source_type']][$entry['source_id']])) {
        return FALSE;
      }
      if ($entry['source_type'] === 'block_content' && isset($hidden_inline_block_ids[$entry['source_id']])) {
        return FALSE;
      }
      if (!$include_old_revisions && $revisionable_types[$entry['source_type']]) {
        $default_revision_id = (int) $existing_ids[$entry['source_type']][$entry['source_id']];
        $entry_latest_affected_vids = $latest_affected_vids[$entry['source_type']][$entry['source_id']] ?? [];
        if (!$this->entryHasCurrentRevisionRecord($entry, $default_revision_id, $entry_latest_affected_vids)) {
          return FALSE;
        }
      }
      return TRUE;
    }));
  }

  /**
   * Batch-computes the latest translation-affected revision ID per language.
   *
   * For each revisionable source type present in $entries, this determines
   * - in one aggregate query for the whole type, not per entity or even per
   * language - the highest revision ID that affected each (entity, language)
   * pair. This is what lets ::entryHasCurrentRevisionRecord() recognize a
   * record that is superseded by a later revision in its own language even
   * when that later revision has no usage record of its own (e.g. it no
   * longer references the target at all), and avoid wrongly treating a
   * translation's own latest revision as superseded just because a later
   * revision in a different language bumped the entity-wide default
   * revision ID past it.
   *
   * @param array<int, array{source_type: string, source_id: int|string, records: mixed[]}> $entries
   *   The candidate usage entries.
   * @param array<string, bool> $revisionable_types
   *   Whether each source type present in $entries is revisionable, keyed by
   *   source type.
   *
   * @return array<string, array<int|string, array<string, int>>>
   *   The latest translation-affected revision ID, keyed by source type,
   *   then source ID, then language code.
   */
  private function getLatestAffectedRevisionIds(array $entries, array $revisionable_types): array {
    $ids_by_type = [];
    foreach ($entries as $entry) {
      $source_type = $entry['source_type'];
      if (empty($revisionable_types[$source_type])) {
        continue;
      }
      $ids_by_type[$source_type][$entry['source_id']] = TRUE;
    }

    $latest_affected_vids = [];
    foreach ($ids_by_type as $source_type => $ids) {
      $storage = $this->entityTypeManager->getStorage($source_type);
      $entity_type = $storage->getEntityType();
      $ids = array_keys($ids);
      $id_key = $entity_type->getKey('id');
      $langcode_key = $entity_type->getKey('langcode');
      $revision_key = $entity_type->getKey('revision');
      $affected_key = $entity_type->getKey('revision_translation_affected');

      // One aggregate query for the whole type: the database groups every
      // revision by (entity, language) and does the max-selection itself,
      // rather than this fetching every matching revision row and reducing
      // it in PHP, or querying per language. For a non-translatable type
      // (or one without the affected key) every revision affects the
      // entity's single language, so the plain latest revision per language
      // - with no further condition - is already the answer.
      $vid_alias = NULL;
      $query = $storage->getAggregateQuery()
        ->allRevisions()
        ->accessCheck(FALSE)
        ->condition($id_key, $ids, 'IN');
      if ($entity_type->isTranslatable() && $affected_key) {
        $query->condition($affected_key, 1);
      }
      $result = $query
        ->aggregate($revision_key, 'MAX', NULL, $vid_alias)
        ->groupBy($id_key)
        ->groupBy($langcode_key)
        ->execute();
      foreach ($result as $row) {
        if (!is_array($row)) {
          continue;
        }
        $latest_affected_vids[$source_type][$row[$id_key]][$row[$langcode_key]] = (int) $row[$vid_alias];
      }
    }

    return $latest_affected_vids;
  }

  /**
   * Checks whether an entry has a record that is still current in its language.
   *
   * A record is current if no later revision has affected its own language;
   * ::getLatestAffectedRevisionIds() gives that threshold per (entity,
   * language), including languages where the target is not referenced in the
   * latest revision at all. This mirrors the revision_group classification
   * ::buildRows() does per-record with a fully loaded entity, reusing the
   * same lookup fetched once for the whole page of entries by
   * ::getLatestAffectedRevisionIds() rather than re-querying per entity.
   *
   * @param array{source_type: string, source_id: int|string, records: mixed[]} $entry
   *   The candidate usage entry.
   * @param int $default_revision_id
   *   The source entity's default revision ID, used as a fallback threshold
   *   for any language ::getLatestAffectedRevisionIds() has no data for.
   * @param array<string, int> $latest_affected_vids_by_lang
   *   The entry's source entity's latest translation-affected revision ID,
   *   keyed by language code, as returned by
   *   ::getLatestAffectedRevisionIds().
   *
   * @return bool
   *   TRUE if at least one record is still current in its own language.
   */
  private function entryHasCurrentRevisionRecord(array $entry, int $default_revision_id, array $latest_affected_vids_by_lang): bool {
    foreach ($entry['records'] as $record) {
      $source_vid = (int) $record['source_vid'];
      // Always current, even if a later, still-pending revision also
      // affects this language: the default revision is what is actually
      // live, independently of any draft in progress. Mirrors the same
      // priority ::buildRows() gives REVISION_DEFAULT over REVISION_OLD.
      if ($source_vid === $default_revision_id) {
        return TRUE;
      }
      $latest_affected_vid = $latest_affected_vids_by_lang[$record['source_langcode']] ?? $default_revision_id;
      if ($source_vid >= $latest_affected_vid) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Build table rows for a set of usage entries.
   *
   * Source entities are batch-loaded per entity type, so this should only be
   * called with the slice of entries actually being displayed (i.e. the
   * current page), not the full set of usage entries for a target entity.
   *
   * @param array<int, array{source_type: string, source_id: int|string, records: mixed[]}> $entries
   *   The usage entries to build rows for. These are expected to have already
   *   been through ::filterDisplayableEntries(), which is where any filtering
   *   that affects the row count/pager total belongs. The only check repeated
   *   here is that the source entity actually loaded, as a safety net.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request. Used to build the "destination" query parameter
   *   forced onto operations links below, so it must reflect this listing
   *   page rather than whatever the operations links themselves are for.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheability metadata for the table rows.
   * @param bool $include_old_revisions
   *   Whether the "Used in" column should mention old-revision usage. Pass
   *   the same value given to ::filterDisplayableEntries() for the entries;
   *   when FALSE, an entry kept only because it also has current usage (see
   *   ::entryHasCurrentRevisionRecord()) will show that current usage without
   *   the old-revision detail, instead of leaving it out of the row entirely.
   * @param bool $has_operations
   *   (optional) Whether the row should show the "Operations" column. This
   *   is passed by reference so that it can be updated to TRUE if any of the
   *   entries has operations.
   *
   * @return mixed[]
   *   An indexed array of rows that should be displayed as sources for this
   *   target entity.
   */
  protected function buildRows(array $entries, Request $request, CacheableMetadata &$cacheable_metadata, bool $include_old_revisions = TRUE, bool &$has_operations = FALSE): array {
    $rows = [];
    if (empty($entries)) {
      return $rows;
    }

    // Tell the Trash module not to hide entities that are trashed.
    if (!is_null($this->trashManager)) {
      $prev_trash_context = $this->trashManager->getTrashContext();
      $this->trashManager->setTrashContext('ignore');
    }

    // \Drupal\Core\Entity\EntityListBuilder::getOperations() sends every
    // operation link through ::ensureDestination(), which prefers a
    // "destination" query parameter already on the current request over the
    // current page. Without one, it already falls back to the current page,
    // which is what we want anyway, so there is nothing to override. But if
    // the current request does carry one (e.g. the entity usage page itself
    // was linked to with a destination), an "Edit" link built below would
    // send the user back wherever that points, rather than back to this
    // listing. Overriding the shared redirect.destination service for the
    // duration of this method forces every operation link generated below to
    // point back here instead.
    $original_destination = NULL;
    if ($request->query->has('destination')) {
      $operations_destination = Url::fromRoute('<current>', [], [
        'query' => $request->query->all(),
      ])->toString();
      $original_destination = $this->getRedirectDestination()->get();
      $this->getRedirectDestination()->set($operations_destination);
    }

    try {
      // Batch load all the source entities per type, instead of loading them
      // one at a time.
      $ids_by_type = [];
      foreach ($entries as $entry) {
        $ids_by_type[$entry['source_type']][] = $entry['source_id'];
      }
      $source_entities = [];
      foreach ($ids_by_type as $source_type => $ids) {
        $source_entities[$source_type] = $this->entityTypeManager->getStorage($source_type)->loadMultiple($ids);
      }

      $entity_types = $this->entityTypeManager->getDefinitions();
      $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);

      foreach ($entries as $entry) {
        $source_type = $entry['source_type'];
        $source_id = $entry['source_id'];
        $records = $entry['records'];

        // We will show a single row per source entity. If the target is not
        // referenced on its default revision on the default language, we will
        // just show indicate that in a specific column.
        $source_entity = $source_entities[$source_type][$source_id] ?? NULL;
        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }

        // The effective published status of the source (or its host, for
        // inline blocks).
        $source_entity_status = $this->getSourceEntityStatus($source_entity);

        $field_definitions = $this->entityFieldManager->getFieldDefinitions($source_type, $source_entity->bundle());
        $default_langcode = $source_entity->language()->getId();
        $used_in = [];
        $revisions = [];
        $source_storage = $this->entityTypeManager->getStorage($source_type);

        if ($source_entity instanceof RevisionableInterface && $source_storage instanceof TranslatableRevisionableStorageInterface) {
          $default_revision_id = (int) $source_entity->getRevisionId();
          // A record is only "old" once a later revision has affected its
          // own language - the entity-wide default revision id is not
          // enough, since a translation can lag behind the default (a newer
          // revision changed a different language) or lead it (a pending
          // draft for this language only).
          // ::getLatestTranslationAffectedRevisionId() is the same source of
          // truth Drupal core itself uses to decide what an editor sees on the
          // edit form for a given translation, so it is correct even when no
          // usage record exists for that revision (e.g. a newer draft that
          // removed the reference entirely).
          $latest_affected_vids = [];
          $old_vids = [];
          foreach ($records as $record) {
            [
              'source_vid' => $source_vid,
              'source_langcode' => $source_langcode,
            ] = $record;
            $source_vid = (int) $source_vid;

            if (!isset($latest_affected_vids[$source_langcode])) {
              $latest_affected_vids[$source_langcode] = (int) $source_storage->getLatestTranslationAffectedRevisionId($source_id, $source_langcode);
            }

            if ($source_vid === $default_revision_id && $source_entity_status !== SourceEntityStatus::Unpublished) {
              // Always current, even if a later, still-pending revision also
              // affects this language: the default revision is what is
              // actually live, independently of any draft in progress.
              $revision_group = static::REVISION_DEFAULT;
            }
            elseif ($source_vid < $latest_affected_vids[$source_langcode]) {
              // A later revision has already changed this translation; this
              // record no longer reflects what an editor would see.
              $revision_group = static::REVISION_OLD;
            }
            else {
              // Either a pending draft ahead of the default revision, or the
              // default revision itself while unpublished (which is really a
              // draft).
              $revision_group = static::REVISION_PENDING;
            }

            if ($revision_group === static::REVISION_OLD) {
              if (!$include_old_revisions) {
                // Old-revision usage is hidden; this record contributes
                // nothing to the row. The entry is only still here because
                // ::filterDisplayableEntries() found at least one other
                // record that is current.
                continue;
              }
              // Record the old vids so we can show the number of distinct
              // revisions.
              $old_vids[$source_vid] = TRUE;
            }
            $revisions[$revision_group][$source_langcode] = TRUE;
          }

          $has_default = !empty($revisions[static::REVISION_DEFAULT]);
          $revision_group_labels = [
            static::REVISION_PENDING => $this->t('Draft revision'),
            static::REVISION_OLD => $this->formatPlural(count($old_vids), '@count old revision', '@count old revisions'),
          ];
          if ($has_default) {
            $used_in[] = $this->summarizeRevisionGroup($default_langcode, $source_entity_status->label(), $revisions[static::REVISION_DEFAULT]);
          }
          foreach ($revision_group_labels as $index => $label) {
            if (!empty($revisions[$index])) {
              $used_in[] = $this->summarizeRevisionGroup($default_langcode, $label, $revisions[$index]);
            }
          }

          if (count($used_in) > 1) {
            $used_in = [
              '#theme' => 'item_list',
              '#items' => $used_in,
              '#list_type' => 'ul',
            ];
          }
        }
        else {
          $used_in[] = $source_entity_status->label();
        }
        // @todo List all the fields that use the target entity.
        $field_name = $records[0]['field_name'];
        $field_label = isset($field_definitions[$field_name])
          ? $field_definitions[$field_name]->getLabel()
          : $this->t('Unknown');

        $type = $entity_types[$source_type]->getLabel();
        if ($source_bundle_key = $source_entity->getEntityType()->getKey('bundle')) {
          $bundle_field = $source_entity->{$source_bundle_key};
          if ($bundle_field->getFieldDefinition()->getType() === 'entity_reference') {
            $bundle_label = $bundle_field->entity->label();
          }
          else {
            $bundle_label = $bundle_field->getString();
          }
          $type .= ': ' . $bundle_label;
        }

        $display_entity = $this->getSourceEntityForDisplay($source_entity);
        $row = [
          $this->getSourceEntityLink($display_entity),
          $type,
          $languages[$default_langcode]->getName(),
          $field_label,
          ['data' => $used_in],
        ];
        $operations = $this->getSourceEntityOperations($display_entity, $cacheable_metadata);
        if (!empty($operations['#links'])) {
          $row[] = ['data' => $operations];
          $has_operations = TRUE;
        }
        $rows[] = $row;
      }
    }
    finally {
      // Restore previous trash context if we changed it.
      if (isset($prev_trash_context)) {
        $this->trashManager->setTrashContext($prev_trash_context);
      }
      if ($original_destination !== NULL) {
        $this->getRedirectDestination()->set($original_destination);
      }
    }

    return $rows;
  }

  /**
   * Returns a render array indicating a revision "type" and languages.
   *
   * For example it might return "Draft revisions (ES, NO)".
   *
   * @param string $default_langcode
   *   The default language code for the referencing entity.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $revision_label
   *   The translated revision-group label, eg 'Old revisions' or the host's
   *   publish-status label.
   * @param bool[] $languages
   *   An array keyed by language codes that reference the entity in the given
   *   type.
   *
   * @return mixed[]
   *   A render array summarizing the information passed in.
   */
  protected function summarizeRevisionGroup(string $default_langcode, TranslatableMarkup $revision_label, array $languages): array {
    $language_objects = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    if (count($languages) === 1 && !empty($languages[$default_langcode])) {
      // If there's only one relevant revision and it's the entity's default
      // language then just show the label.
      return ['#plain_text' => $revision_label];
    }
    else {
      // Otherwise show the languages enumerated, ensuring the default language
      // comes first if present.
      if (!empty($languages[$default_langcode])) {
        $languages = [$default_langcode => TRUE] + $languages;
      }
      // Ignore not installed languages.
      $languages = array_intersect_key($languages, $language_objects);
      return [
        '#type' => 'inline_template',
        '#template' => '{{ label }} ({% for language in languages %}{{ language }}{{ loop.last ? "" : ", " }}{% endfor %})',
        '#context' => [
          'label' => $revision_label,
          'languages' => array_map(fn ($code) => [
            '#type' => 'inline_template',
            '#template' => '<abbr title="{{ name|e("html_attr") }}">{{ code }}</abbr>',
            '#context' => [
              'code' => mb_strtoupper($code),
              'name' => $language_objects[$code]->getName(),
            ],
          ], array_keys($languages)),
        ],
      ];
    }
  }

  /**
   * Title page callback.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int|string $entity_id
   *   The entity id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title to be used on this page.
   */
  public function getTitle(string $entity_type, int|string $entity_id): TranslatableMarkup {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity) {
      return $this->t('Entity usage information for %entity_label', ['%entity_label' => $entity->label()]);
    }
    return $this->t('Entity Usage List');
  }

  /**
   * Retrieve the source entity's status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return \Drupal\entity_usage\SourceEntityStatus
   *   The source entity's status.
   */
  protected function getSourceEntityStatus(EntityInterface $source_entity): SourceEntityStatus {
    // Use the status from the host entity for inline content blocks.
    if ($source_entity instanceof BlockContentInterface && !$source_entity->isReusable()) {
      $parent = $this->getContentBlockParentEntity($source_entity);
      if (!empty($parent)) {
        return $this->getSourceEntityStatus($parent);
      }
    }

    if ($source_entity instanceof EntityPublishedInterface) {
      return $source_entity->isPublished() ? SourceEntityStatus::Published : SourceEntityStatus::Unpublished;
    }
    return SourceEntityStatus::Current;
  }

  /**
   * Retrieve the entity to use for display, in place of a source entity.
   *
   * Note that some entities are special-cased, since they don't have canonical
   * template and aren't expected to be re-usable. For example, if the entity
   * passed in is a block content entity, the entity returned will be this
   * entity's parent (host) entity instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity to display in place of the source entity: either the source
   *   entity itself, or its host entity, when one can be found.
   */
  protected function getSourceEntityForDisplay(EntityInterface $source_entity): EntityInterface {
    // Treat block_content entities in a special manner. Block content
    // relationships are stored as serialized data on the host entity. This
    // makes it difficult to query parent data. Instead we look up relationship
    // data which may exist in entity_usage tables. This requires site builders
    // to set up entity usage on host-entity-type -> block_content manually.
    // @todo this could be made more generic to support other entity types with
    // difficult to handle parent -> child relationships.
    if ($source_entity instanceof BlockContentInterface && !$source_entity->isReusable()) {
      $parent = $this->getContentBlockParentEntity($source_entity);
      if ($parent) {
        return $this->getSourceEntityForDisplay($parent);
      }
    }
    return $source_entity;
  }

  /**
   * Retrieve a link to the source entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return \Drupal\Core\Link|string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   A link to the entity, or its non-linked label, in case it was impossible
   *   to correctly build a link.
   */
  protected function getSourceEntityLink(EntityInterface $source_entity): Link|string|TranslatableMarkup {
    $entity = $this->getSourceEntityForDisplay($source_entity);
    if ($source_entity !== $entity) {
      @trigger_error('Calling \Drupal\entity_usage\Controller\ListUsageController::getSourceEntityForDisplay() without calling ::getSourceEntityLink() first is deprecated in entity_usage:5.0.1 and is removed from entity_usage:6.0.0. There is no replacement. See https://www.drupal.org/node/3410072', E_USER_DEPRECATED);
    }

    $entity_in_trash = !is_null($this->trashManager) && Trash::entityIsDeleted($entity);
    $entity_label = $entity->access('view label') ? $entity->label() : $this->t('- Restricted access -');
    if ($entity_in_trash) {
      $entity_label .= ' ' . $this->t('(in trash)');
    }

    $rel = NULL;
    if ($entity->hasLinkTemplate('revision')) {
      $rel = 'revision';
    }
    elseif ($entity->hasLinkTemplate('canonical')) {
      $rel = 'canonical';
    }

    // Block content likely used in Layout Builder inline or reusable blocks.
    if ($entity instanceof BlockContentInterface) {
      $rel = NULL;
    }

    if ($rel) {
      // Prevent 404s by exposing the text unlinked if the user has no access
      // to view the entity.
      $options = [];
      if ($entity_in_trash) {
        // Trashed entities need a query string parameter to allow viewing.
        $options['query'] = ['in_trash' => TRUE];
      }
      return $entity->access('view') ? $entity->toLink($entity_label, $rel, $options) : $entity_label;
    }

    // As a fallback just return a non-linked label.
    return $entity_label;
  }

  /**
   * Figure out the "parent" entity of a content block.
   *
   * @param \Drupal\block_content\BlockContentInterface $block_content
   *   The block entity we are interested in.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity that has a tracked relationship pointing to this block.
   */
  private function getContentBlockParentEntity(BlockContentInterface $block_content): ?EntityInterface {
    $sources = $this->entityUsage->listSources($block_content, FALSE);
    $source = reset($sources);
    if (!empty($source['source_type']) && !empty($source['source_id'])) {
      return $this->entityTypeManager()->getStorage($source['source_type'])->load($source['source_id']);
    }
    return NULL;
  }

  /**
   * Checks access based on whether the user can view the current entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int|string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(string $entity_type, int|string $entity_id): AccessResultInterface {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      return AccessResult::forbidden();
    }
    return $entity->access('view', NULL, TRUE);
  }

  /**
   * Retrieve the operations links for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheable metadata object.
   *
   * @return mixed[]
   *   A render array for the entity's operations links; an empty array if the
   *   entity type has no list builder.
   */
  protected function getSourceEntityOperations(EntityInterface $entity, CacheableMetadata $cacheable_metadata): array {
    if (!$entity->getEntityType()->hasHandlerClass('list_builder')) {
      return [];
    }

    $operations = $this->entityTypeManager->getListBuilder($entity->getEntityTypeId())->getOperations($entity, $cacheable_metadata);
    return [
      '#type' => 'operations',
      '#links' => $operations,
    ];
  }

}
