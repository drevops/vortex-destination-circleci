<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\entity_usage\Controller\ListUsageController;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests filtering old-revision usage on the listing page.
 *
 * Old revisions are hidden by default; a usage entry is only dropped for
 * this reason when none of its records reference the source entity's
 * default (or a newer, pending) revision.
 *
 * @coversDefaultClass \Drupal\entity_usage\Controller\ListUsageController
 *
 * @group entity_usage
 */
#[Group('entity_usage')]
#[RunTestsInSeparateProcesses]
class ListUsageControllerRevisionFilterTest extends KernelTestBase {

  use NodeCreationTrait {
    createNode as drupalCreateNode;
  }
  use UserCreationTrait {
    createUser as drupalCreateUser;
  }
  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'datetime',
    'user',
    'system',
    'filter',
    'field',
    'text',
    'language',
    'entity_usage',
  ];

  /**
   * The target node every synthetic usage record points to.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $target;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['filter', 'node', 'user']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig('entity_usage');

    // Create and discard a first user so it consumes uid 1, which Drupal
    // always treats as a superuser bypassing every permission check. See
    // ListUsageControllerCheckAccessTest for the same reasoning.
    $this->drupalCreateUser();

    $this->drupalCreateContentType(['type' => 'page']);
    $this->setCurrentUser($this->drupalCreateUser(['access content', 'bypass node access']));

    $this->target = $this->drupalCreateNode(['type' => 'page', 'title' => 'Target']);
  }

  /**
   * Registers a synthetic usage record pointing at $this->target.
   *
   * Bypasses actual field storage: entity_usage's own listSources() only
   * cares about the rows in the entity_usage table, not real field data, so
   * this is enough to build arbitrary (source, vid) combinations quickly.
   */
  protected function registerUsage(string|int $source_id, int $source_vid, string $field_name = 'field_test', string $langcode = 'en'): void {
    $this->container->get('entity_usage.usage')->registerUsage(
      $this->target->id(),
      'node',
      $source_id,
      'node',
      $langcode,
      $source_vid,
      'entity_reference',
      $field_name,
    );
  }

  /**
   * Builds a request for the target's listing page and pushes it as current.
   *
   * Both the controller (for the "list_old_revisions" argument) and the
   * pager manager (for the "page" argument) read the current request, so
   * both need it on the request stack, not just passed to the method call.
   *
   * A session is attached because EntityUsageFilterForm goes through the
   * form API (::formBuilder()->getForm()), which needs one on the request,
   * e.g. for CSRF tokens and status messages; a bare Request::create() has
   * none, see FormActionXssTest for the same pattern in core.
   */
  protected function pushRequest(array $query): Request {
    $request = Request::create('/admin/content/entity-usage/node/' . $this->target->id(), 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    return $request;
  }

  /**
   * Gets the source entity titles shown in a listing page's table, in order.
   *
   * @param mixed[] $build
   *   The render array returned by ListUsageController::listUsagePage().
   *
   * @return string[]
   *   The source entity titles, in table order.
   */
  protected function getRowTitles(array $build): array {
    return array_map(
      fn (array $row) => (string) $row[0]->getText(),
      $build['table']['#rows'] ?? [],
    );
  }

  /**
   * Renders a render array to plain text, for asserting on its content.
   */
  protected function renderToString(array $element): string {
    return (string) $this->container->get('renderer')->renderInIsolation($element);
  }

  /**
   * @covers ::getDisplayableEntries
   * @covers ::filterDisplayableEntries
   */
  public function testOldRevisionsAreHiddenByDefaultButKeptWhenAlsoCurrent(): void {
    // Only the default revision references the target: always shown.
    $node_a = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source A']);
    $this->registerUsage($node_a->id(), (int) $node_a->getRevisionId());

    // Only a superseded revision references the target: hidden by default.
    $node_b = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source B']);
    $old_vid_b = (int) $node_b->getRevisionId();
    // A real field change is needed for Drupal to mark this revision as
    // affecting the 'en' translation - a bare resave with no change would
    // not, and ::getLatestAffectedRevisionIds() would then still see
    // $old_vid_b as the latest affected revision for 'en', wrongly leaving
    // its usage current.
    $node_b->setTitle('Source B updated');
    $node_b->setNewRevision(TRUE);
    $node_b->save();
    $this->registerUsage($node_b->id(), $old_vid_b);

    // Referenced from both a superseded revision and the default one: the
    // entry is still current, so it must never be dropped.
    $node_c = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source C']);
    $old_vid_c = (int) $node_c->getRevisionId();
    $node_c->setTitle('Source C updated');
    $node_c->setNewRevision(TRUE);
    $node_c->save();
    $this->registerUsage($node_c->id(), $old_vid_c, 'field_old');
    $this->registerUsage($node_c->id(), (int) $node_c->getRevisionId(), 'field_new');

    // Default request: old-revision-only usage (node B) is hidden.
    $titles = $this->renderPage([]);
    sort($titles);
    $this->assertSame(['Source A', 'Source C updated'], $titles);

    // Opting in via the query argument brings node B back.
    $titles = $this->renderPage(['list_old_revisions' => '1']);
    sort($titles);
    $this->assertSame(['Source A', 'Source B updated', 'Source C updated'], $titles);
  }

  /**
   * @covers ::buildRows
   */
  public function testUsedInColumnOmitsOldRevisionDetailUnlessIncluded(): void {
    // Referenced from both a superseded revision and the default one: the
    // row is shown either way (see the previous test), but the "Used in"
    // detail for the superseded revision should only appear once opted in.
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source']);
    $old_vid = (int) $node->getRevisionId();
    // A real field change, so this revision is recognized as affecting the
    // 'en' translation (see the comment in
    // testOldRevisionsAreHiddenByDefaultButKeptWhenAlsoCurrent()).
    $node->setTitle('Source updated');
    $node->setNewRevision(TRUE);
    $node->save();
    $this->registerUsage($node->id(), $old_vid, 'field_old');
    $this->registerUsage($node->id(), (int) $node->getRevisionId(), 'field_new');

    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest([]));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringNotContainsString('old revision', $used_in);

    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest(['list_old_revisions' => '1']));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringContainsString('1 old revision', $used_in);
  }

  /**
   * @covers ::buildRows
   * @covers ::filterDisplayableEntries
   */
  public function testDefaultRevisionUsageStaysCurrentDespiteLaterPendingDraftInSameLanguage(): void {
    // Regression test: a usage record that exactly matches the default
    // revision must always be shown as current ("Published revision"), even
    // when a later, still-pending revision also affects the same language -
    // that later revision is a draft in progress, not a replacement for
    // what is actually live. Getting this wrong doesn't just mislabel the
    // row: ::filterDisplayableEntries() would drop it entirely once it is
    // the only usage record and old revisions are hidden, since nothing
    // else would register as "current".
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source']);
    $default_vid = (int) $node->getRevisionId();
    $this->registerUsage($node->id(), $default_vid);

    // A later pending draft, still referencing the target, that does not
    // become the new default - e.g. a moderated node's "Create new draft"
    // transition, which affects 'en' (the moderation state changed) without
    // superseding the published revision.
    $node->setTitle('Source draft');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();
    $this->registerUsage($node->id(), (int) $node->getRevisionId());

    // Old revisions hidden (default): both records are current, so the row
    // stays, and both "Published revision" and "Draft revision" appear -
    // the default's record must not be demoted to old just because a later
    // revision exists.
    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest([]));
    $this->assertSame(['Source'], $this->getRowTitles($build));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringContainsString('Draft revision', $used_in);
    $this->assertStringNotContainsString('old revision', $used_in);

    // Old revisions included: nothing here is actually old, so the count
    // must not appear even when opted in.
    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest(['list_old_revisions' => '1']));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringContainsString('Draft revision', $used_in);
    $this->assertStringNotContainsString('old revision', $used_in);
  }

  /**
   * @covers ::buildRows
   */
  public function testDemotedPendingDraftDoesNotHideLaterDefaultOrPendingRecords(): void {
    // Two languages, each with its own pending draft; the older of the two
    // 'en' drafts is superseded by the newer one, so ::buildRows() demotes
    // it to "old" even though its own vid is still above the default
    // revision. A genuinely-old record (below the default revision) can
    // safely short-circuit the rest of the loop once found, because records
    // are ordered by vid descending; a record demoted this way cannot,
    // since records for other languages - still at or above the default
    // revision - can (and here do) come after it.
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source']);
    $default_vid = (int) $node->getRevisionId();

    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();
    $superseded_en_vid = (int) $node->getRevisionId();

    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();
    $fr_vid = (int) $node->getRevisionId();

    // A real field change, so this revision is recognized as affecting the
    // 'en' translation and correctly supersedes $superseded_en_vid (see the
    // comment in testOldRevisionsAreHiddenByDefaultButKeptWhenAlsoCurrent()).
    $node->setTitle('Source updated');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();
    $latest_en_vid = (int) $node->getRevisionId();

    // Registered in descending vid order, matching how ::listSources()
    // returns them: the latest 'en' draft, then the only 'fr' draft, then
    // the superseded 'en' draft (demoted to old), then the default revision
    // - in a different language again, so it is not conflated with either
    // 'en' record.
    $this->registerUsage($node->id(), $latest_en_vid, 'field_en_new', 'en');
    $this->registerUsage($node->id(), $fr_vid, 'field_fr_new', 'fr');
    $this->registerUsage($node->id(), $superseded_en_vid, 'field_en_old', 'en');
    $this->registerUsage($node->id(), $default_vid, 'field_de_default', 'de');

    // Old revisions hidden: the row stays (it has current usage), the
    // default-revision usage is still counted despite coming after the
    // demoted record in iteration order, and no old-revision detail leaks
    // in from the demoted record.
    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest([]));
    $this->assertSame(['Source'], $this->getRowTitles($build));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringContainsString('Draft revision', $used_in);
    $this->assertStringNotContainsString('old revision', $used_in);

    // Old revisions included: the demoted record now shows up as one old
    // revision, alongside the same default and draft usage as before.
    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest(['list_old_revisions' => '1']));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringContainsString('Draft revision', $used_in);
    $this->assertStringContainsString('1 old revision', $used_in);
  }

  /**
   * @covers ::buildRows
   * @covers ::filterDisplayableEntries
   */
  public function testSupersededDraftIsOldEvenWhenNewestRevisionHasNoUsage(): void {
    // Regression test for #3624974: a draft's usage record must be treated
    // as old once a later revision exists, even when that later revision
    // does not reference the target at all - so there is no usage record
    // for it to be compared against.
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source']);

    // A draft that references the target.
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();
    $superseded_vid = (int) $node->getRevisionId();
    $this->registerUsage($node->id(), $superseded_vid);

    // A later draft that removes the reference again: no usage record of
    // its own, but it still supersedes the previous draft. The title change
    // is what makes Drupal recognize this revision as affecting the 'en'
    // translation (see the comment in
    // testOldRevisionsAreHiddenByDefaultButKeptWhenAlsoCurrent()) - without
    // it, this revision would not actually supersede $superseded_vid.
    $node->setTitle('Source updated');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();

    // Old revisions hidden (default): the entry's only usage is superseded,
    // so it is dropped entirely, the same as any other old-revision-only
    // entry (see testFilterFormShownEvenWhenOldRevisionsAreTheOnlyUsage()).
    $titles = $this->renderPage([]);
    $this->assertSame([], $titles);

    // Old revisions included: the row appears, correctly labelled as old -
    // not as a current draft.
    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest(['list_old_revisions' => '1']));
    $this->assertSame(['Source'], $this->getRowTitles($build));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('1 old revision', $used_in);
    $this->assertStringNotContainsString('Draft revision', $used_in);
  }

  /**
   * @covers ::buildRows
   * @covers ::filterDisplayableEntries
   */
  public function testTranslationDraftUsageStaysCurrentWhenAnotherLanguagePublishes(): void {
    // Regression test: a translation's own latest revision can have a lower
    // vid than the entity-wide default revision, when a later revision only
    // affected a different language (e.g. a plain node edit form always
    // forks a new draft from the default revision, not from a pending
    // draft in another language). Usage recorded against that
    // translation's own latest revision must stay current - it must not be
    // treated as old just because an unrelated later revision moved the
    // default past it.
    //
    // Four languages, each exercising a different combination, to make sure
    // ::getLatestAffectedRevisionIds()'s single aggregate query - grouped by
    // (entity, language) - keeps every language's MAX(vid) independent
    // rather than conflating them:
    // - 'en': matches the final default revision - REVISION_DEFAULT.
    // - 'fr': superseded by a later revision that also affects 'fr' -
    //   REVISION_OLD, the ordinary same-language demotion case.
    // - 'de': a genuine pending draft, ahead of the default - REVISION_PENDING.
    // - 'es': untouched since creation, so its own latest affected revision
    //   is BELOW the default (which advanced for 'en' only) - this is the
    //   bug this test guards against, and lands in REVISION_PENDING too
    //   (not the default bucket, since its vid does not match the default's).
    foreach (['fr', 'de', 'es'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }

    // Published in all four languages.
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source EN']);
    $node->addTranslation('fr', ['title' => 'Source FR']);
    $node->addTranslation('de', ['title' => 'Source DE']);
    $node->addTranslation('es', ['title' => 'Source ES']);
    $node->save();
    $creation_vid = (int) $node->getRevisionId();

    // 'es' usage recorded against the original, still-published revision;
    // nothing ever touches 'es' again, so this stays its latest affected
    // revision throughout the rest of the test.
    $this->registerUsage($node->id(), $creation_vid, 'field_es', 'es');

    // A draft that only changes 'fr', later superseded by another 'fr'
    // change below - this record must end up "old", not "current".
    $node = Node::load($node->id());
    $node->getTranslation('fr')->setTitle('Source FR draft 1');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();
    $fr_superseded_vid = (int) $node->getRevisionId();
    $this->registerUsage($node->id(), $fr_superseded_vid, 'field_fr_old', 'fr');

    // Supersedes the 'fr' draft above, forked from the default (not from
    // the previous 'fr' draft) - matching the plain node edit form. No
    // usage record of its own, mirroring
    // testSupersededDraftIsOldEvenWhenNewestRevisionHasNoUsage().
    $node = Node::load($node->id());
    $node->getTranslation('fr')->setTitle('Source FR draft 2');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();

    // A pending draft that only changes 'de' - stays current and pending,
    // since nothing ever supersedes it.
    $node = Node::load($node->id());
    $node->getTranslation('de')->setTitle('Source DE draft');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(FALSE);
    $node->save();
    $de_draft_vid = (int) $node->getRevisionId();
    $this->registerUsage($node->id(), $de_draft_vid, 'field_de', 'de');

    // A later draft, forked from the still-published default (not from any
    // of the pending drafts above), that only changes 'en' and is
    // published immediately. Its vid is higher than the 'fr' and 'de'
    // drafts', even though it never affected either translation.
    $node = Node::load($node->id());
    $node->getTranslation('en')->setTitle('Source EN published');
    $node->setNewRevision(TRUE);
    $node->isDefaultRevision(TRUE);
    $node->save();
    $default_vid = (int) $node->getRevisionId();
    $this->registerUsage($node->id(), $default_vid, 'field_en', 'en');

    // Old revisions hidden (default): the 'fr' draft was superseded, so its
    // usage is dropped, but the row itself must not be - it still has
    // current usage in 'en', 'de' and 'es'. 'en' is the entity's own default
    // language, so it renders as a plain label with no language code; 'de'
    // and 'es' are grouped together under "Draft revision", each named by
    // its own code, confirming the aggregate query kept them independent
    // instead of conflating them.
    $titles = $this->renderPage([]);
    $this->assertSame(['Source EN published'], $titles);

    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest([]));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringContainsString('Draft revision (<abbr title="German">DE</abbr>, <abbr title="Spanish">ES</abbr>)', $used_in);
    $this->assertStringNotContainsString('>FR</abbr>', $used_in);
    $this->assertStringNotContainsString('old revision', $used_in);

    // Old revisions included: the superseded 'fr' draft now shows up too,
    // named by its own code and kept apart from the 'de'/'es' drafts.
    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest(['list_old_revisions' => '1']));
    $used_in = $this->renderToString($build['table']['#rows'][0][4]['data']);
    $this->assertStringContainsString('Published revision', $used_in);
    $this->assertStringContainsString('Draft revision (<abbr title="German">DE</abbr>, <abbr title="Spanish">ES</abbr>)', $used_in);
    $this->assertStringContainsString('1 old revision (<abbr title="French">FR</abbr>)', $used_in);
  }

  /**
   * @covers ::listUsagePage
   */
  public function testFilterFormShownEvenWhenOldRevisionsAreTheOnlyUsage(): void {
    // The only usage exists solely in a superseded revision, so the default
    // (old revisions hidden) request has nothing to show. The filter form
    // must still appear, otherwise there is no way to discover it exists.
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source']);
    $old_vid = (int) $node->getRevisionId();
    // A real field change, so this revision is recognized as affecting the
    // 'en' translation (see the comment in
    // testOldRevisionsAreHiddenByDefaultButKeptWhenAlsoCurrent()).
    $node->setTitle('Source updated');
    $node->setNewRevision(TRUE);
    $node->save();
    $this->registerUsage($node->id(), $old_vid);

    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest([]));
    $this->assertArrayHasKey('filter_form', $build);
    $this->assertFalse($build['filter_form']['list_old_revisions']['#default_value']);
    $this->assertArrayHasKey('empty', $build);
    $this->assertArrayNotHasKey('table', $build);

    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest(['list_old_revisions' => '1']));
    $this->assertTrue($build['filter_form']['list_old_revisions']['#default_value']);
    $this->assertSame(['Source updated'], $this->getRowTitles($build));
  }

  /**
   * @covers ::listUsagePage
   */
  public function testListingIsHtmxEnabledForProgressiveEnhancement(): void {
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Source']);
    $this->registerUsage($node->id(), (int) $node->getRevisionId());

    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest([]));

    // The whole listing is wrapped in a container HTMX can swap in place.
    $this->assertSame('container', $build['#type']);
    $this->assertSame(ListUsageController::HTMX_LIST_ID, $build['#attributes']['id']);

    // The checkbox itself carries the HTMX wiring and pulls in its library.
    $checkbox = $build['filter_form']['list_old_revisions'];
    $this->assertContains('core/drupal.htmx', $checkbox['#attached']['library'] ?? []);
    $this->assertSame('change', (string) $checkbox['#attributes']['data-hx-trigger']);
    $this->assertStringContainsString('#' . ListUsageController::HTMX_LIST_ID, (string) $checkbox['#attributes']['data-hx-target']);
  }

  /**
   * Renders one page of the listing and returns the titles shown on it.
   *
   * Uses a fresh controller per call: ::$displayableEntries is cached for
   * the lifetime of a controller instance, which is correct for a single
   * real request but would return stale results here, across calls with
   * different query arguments.
   *
   * @param array<string, mixed> $query
   *   Query arguments for the request, e.g. ['page' => 1].
   *
   * @return string[]
   *   The source entity titles, in table order.
   */
  protected function renderPage(array $query): array {
    $controller = ListUsageController::create($this->container);
    $build = $controller->listUsagePage('node', $this->target->id(), $this->pushRequest($query));
    return $this->getRowTitles($build);
  }

  /**
   * @covers ::listUsagePage
   * @covers ::getDisplayableEntries
   */
  public function testFilterChangesPagerTotalsAndPageBoundaries(): void {
    $this->config('entity_usage.settings')
      ->set('usage_controller_items_per_page', 3)
      ->save();

    // 5 entries that remain regardless of the filter.
    $current_titles = [];
    for ($i = 1; $i <= 5; $i++) {
      $node = $this->drupalCreateNode(['type' => 'page', 'title' => "Current $i"]);
      $this->registerUsage($node->id(), (int) $node->getRevisionId());
      $current_titles[] = "Current $i";
    }

    // 3 entries that only exist in a superseded revision.
    $old_titles = [];
    for ($i = 1; $i <= 3; $i++) {
      $node = $this->drupalCreateNode(['type' => 'page', 'title' => "Old $i"]);
      $old_vid = (int) $node->getRevisionId();
      // A real field change, so this revision is recognized as affecting
      // the 'en' translation (see the comment in
      // testOldRevisionsAreHiddenByDefaultButKeptWhenAlsoCurrent()).
      $node->setTitle("Old $i updated");
      $node->setNewRevision(TRUE);
      $node->save();
      $this->registerUsage($node->id(), $old_vid);
      $old_titles[] = "Old $i updated";
    }

    // Old revisions hidden (default): 5 entries, 2 pages of 3 + 2.
    $page0 = $this->renderPage(['page' => 0]);
    $page1 = $this->renderPage(['page' => 1]);
    $this->assertCount(3, $page0);
    $this->assertCount(2, $page1);
    $seen = array_merge($page0, $page1);
    sort($seen);
    $this->assertSame($current_titles, $seen);

    // Old revisions included: 8 entries, 3 pages of 3 + 3 + 2.
    $query = ['list_old_revisions' => '1'];
    $page0 = $this->renderPage($query + ['page' => 0]);
    $page1 = $this->renderPage($query + ['page' => 1]);
    $page2 = $this->renderPage($query + ['page' => 2]);
    $this->assertCount(3, $page0);
    $this->assertCount(3, $page1);
    $this->assertCount(2, $page2);
    $seen = array_merge($page0, $page1, $page2);
    sort($seen);
    $this->assertSame(array_merge($current_titles, $old_titles), $seen);
  }

}
