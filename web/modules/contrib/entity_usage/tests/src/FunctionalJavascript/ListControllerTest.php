<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the page listing the usage of a given entity.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
#[Group('entity_usage')]
#[RunTestsInSeparateProcesses]
class ListControllerTest extends EntityUsageJavascriptTestBase {

  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Grant the logged-in user permission to see the statistics page.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, ['access entity usage statistics']);
  }

  /**
   * Tests the page listing the usage of entities.
   *
   * @covers \Drupal\entity_usage\Controller\ListUsageController::listUsagePage
   */
  public function testListController(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    /** @var \Drupal\node\NodeInterface $node1 */
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Create node 2 referencing node 1 using reference field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', 'Node 1 (1)');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been created.');
    $node2 = $this->getLastEntityOfType('node', TRUE);

    // Create node 3 also referencing node 1 in an embed text field.
    $uuid_node1 = $node1->uuid();
    $embedded_text = '<drupal-entity data-embed-button="node" data-entity-embed-display="entity_reference:entity_reference_label" data-entity-embed-display-settings="{&quot;link&quot;:1}" data-entity-type="node" data-entity-uuid="' . $uuid_node1 . '"></drupal-entity>';
    $node3 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 3',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node3->save();

    // Visit the page that tracks usage of node 1 and check everything is there.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextContains('Entity usage information for Node 1');

    // Check table headers are present.
    $assert_session->pageTextContains('Entity');
    $assert_session->pageTextContains('Type');
    $assert_session->pageTextContains('Language');
    $assert_session->pageTextContains('Field name');

    // Make sure that all elements of the table are the expected ones.
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 3', $first_row_title_link->getText());
    $this->assertStringContainsString($node3->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Text', $first_row_field_label->getText());
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published revision', $first_row_status->getText());

    $second_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[2]/td[1]/a');
    $this->assertEquals('Node 2', $second_row_title_link->getText());
    $this->assertStringContainsString($node2->toUrl()->toString(), $second_row_title_link->getAttribute('href'));
    $second_row_type = $this->xpath('//table/tbody/tr[2]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $second_row_type->getText());
    $second_row_langcode = $this->xpath('//table/tbody/tr[2]/td[3]')[0];
    $this->assertEquals('English', $second_row_langcode->getText());
    $second_row_field_label = $this->xpath('//table/tbody/tr[2]/td[4]')[0];
    $this->assertEquals('Related nodes', $second_row_field_label->getText());
    $second_row_status = $this->xpath('//table/tbody/tr[2]/td[5]')[0];
    $this->assertEquals('Published revision', $second_row_status->getText());

    // If we unpublish Node 2 its status is correctly reflected.
    /** @var \Drupal\node\NodeInterface $node2 */
    $node2->setUnpublished()->save();
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $second_row_status = $this->xpath('//table/tbody/tr[2]/td[5]')[0];
    $this->assertEquals('Draft revision', $second_row_status->getText());

    // Artificially create some garbage in the database and make sure it doesn't
    // show up on the usage page.
    \Drupal::database()->insert('entity_usage')
      ->fields([
        'target_id' => $node1->id(),
        'target_type' => $node1->getEntityTypeId(),
        'source_id' => '1234',
        'source_type' => 'user',
        'source_langcode' => 'en',
        'source_vid' => '5678',
        'method' => 'entity_reference',
        'field_name' => 'field_foo',
        'count' => '1',
      ])
      ->execute();
    // Check the usage is there.
    $usage = \Drupal::service('entity_usage.usage')->listSources($node1);
    $this->assertNotEmpty($usage['user']);
    // Check the usage list skips it when showing results.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextContains('Entity usage information for Node 1');
    $assert_session->elementNotContains('css', 'table', '1234');
    $assert_session->elementNotContains('css', 'table', 'user');
    $assert_session->elementNotContains('css', 'table', '5678');
    $assert_session->elementNotContains('css', 'table', 'field_foo');

    // If some sources reference our entity only in a previous revision, that
    // source is hidden entirely by default.
    // @phpstan-ignore-next-line
    $node2->field_eu_test_related_nodes = NULL;
    $node2->setNewRevision();
    $node2->save();
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextNotContains('Node 2');
    $this->assertEquals(1, count($this->xpath('//table/tbody/tr')));

    // Checking "Show usage in old revisions" brings it back, with an
    // additional column showing where the old usage is. HTMX submits on
    // change, so there is no button to press.
    $page->checkField('Show usage in old revisions');
    $session->wait(500);
    $assert_session->pageTextContains('Used in');
    $first_row_used_in = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published revision', $first_row_used_in->getText());
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[5]')[0];
    $this->assertEquals('1 old revision', $second_row_used_in->getText());
    $this->assertEquals(2, count($this->xpath('//table/tbody/tr')));

    // Create an additional language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Let the logged-in user do multi-lingual stuff.
    /** @var \Drupal\user\RoleInterface $authenticated_role */
    $authenticated_role = Role::load('authenticated');
    $authenticated_role->grantPermission('administer content translation');
    $authenticated_role->grantPermission('translate any entity');
    $authenticated_role->grantPermission('create content translations');
    $authenticated_role->grantPermission('administer languages');
    $authenticated_role->grantPermission('administer entity usage');
    $authenticated_role->grantPermission('access entity usage statistics');
    $authenticated_role->save();

    // Set our content type as translatable.
    $this->drupalGet('/admin/config/regional/content-language');
    $page->checkField('entity_types[node]');
    $assert_session->elementExists('css', '#edit-settings-node')->click();
    $page->checkField('settings[node][eu_test_ct][translatable]');
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Settings successfully updated.');

    // Translate $node2 and check its translation doesn't show up.
    $this->drupalGet("/es/node/{$node2->id()}/translations/add/en/es");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision (all languages)');
    $assert_session->checkboxChecked('Create new revision (all languages)');
    $page->pressButton('Save (this translation)');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');

    // Node 2 now also has a current reference (via its ES translation), so
    // it shows up even with old revisions hidden; but the old-revision
    // detail from before stays hidden, since it wasn't asked for.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextContains('Used in');
    $first_row_used_in = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published revision', $first_row_used_in->getText());
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[5]')[0];
    $this->assertEquals('Draft revision (ES)', $second_row_used_in->getText());
    $this->assertEquals(2, count($this->xpath('//table/tbody/tr')));

    // The old-revision detail is still there when explicitly requested.
    $page->checkField('Show usage in old revisions');
    $session->wait(500);
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[5]')[0];
    $this->assertEquals('Draft revision (ES) 1 old revision', $second_row_used_in->getText());

    // Verify that it's possible to control the number of items per page.
    // Initially we have no pager since two rows fit in one page.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->elementNotExists('css', 'ul.pager__items');
    $this->drupalGet('/admin/config/entity-usage/settings');
    // Set items per page to 1.
    $page->find('css', 'input[name="usage_controller_items_per_page"]')
      ->setValue('1');
    $page->find('css', 'details#edit-track-enabled-source-entity-types summary')->click();
    $page->checkField('track_enabled_source_entity_types[entity_types][user]');
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    // Pager is there.
    $pager_element = $assert_session->elementExists('css', 'ul.pager__items');
    // First node is on the first page, the second node on the next page.
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 3', $first_row_title_link->getText());
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');
    $pager_element->find('css', '.pager__item--next a')->click();
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 2', $first_row_title_link->getText());
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');

    // Toggling "Show usage in old revisions" while on the second page resets
    // the pager back to the first page, rather than leaving it on a page
    // index that may no longer make sense for the new total. HTMX submits on
    // change, so there is no button to press.
    $page->checkField('Show usage in old revisions');
    $session->wait(500);
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 3', $first_row_title_link->getText());
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');

    // Same in the other direction: starting from an explicit second page
    // with old revisions included, unchecking resets back to the first page.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}", [
      'query' => ['list_old_revisions' => 1, 'page' => 1],
    ]);
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 2', $first_row_title_link->getText());
    $page->uncheckField('Show usage in old revisions');
    $session->wait(500);
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 3', $first_row_title_link->getText());
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');

    // A pager link rendered from an HTMX-swapped fragment could otherwise
    // carry HTMX's own request-negotiation query arguments (see
    // core/misc/htmx/htmx-assets.js's "htmx:configRequest" handler) into its
    // own href, since core's PagerManager::getUpdatedParameters() merges the
    // entire current request's query string into every pager link it
    // builds. Most importantly, "_wrapper_format=drupal_htmx" ending up in
    // that href would make a plain click on it - a normal, non-HTMX
    // navigation - receive a bare content fragment instead of a full page,
    // breaking every asset on the page that link leads to (including HTMX
    // itself, silently breaking the filter checkbox from then on).
    // ListUsageController strips these from the request when it detects the
    // request that renders them is itself an HTMX request.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $page->checkField('Show usage in old revisions');
    $session->wait(500);
    $pager_element = $assert_session->elementExists('css', 'ul.pager__items');
    $pager_element->find('css', '.pager__item--next a')->click();
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 2', $first_row_title_link->getText());
    // A full page, not a bare HTMX content fragment: HTMX's own script (and
    // everything else) loaded, so the checkbox below still works.
    $this->assertGreaterThan(1, $this->getSession()->evaluateScript('return document.scripts.length;'));
    $assert_session->checkboxChecked('Show usage in old revisions');
    $page->uncheckField('Show usage in old revisions');
    $session->wait(500);
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 3', $first_row_title_link->getText());
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');
    $assert_session->checkboxNotChecked('Show usage in old revisions');

    $this->rebuildAll();
    // Set reference on bundleless user entity referencing node 1.
    $this->loggedInUser->set('field_eu_test_related_nodes', [
      'target_id' => $node1->id(),
    ])->save();

    // Check this reference shows up on usage page, without bundle label.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}", ['query' => ['page' => '2']]);
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($this->loggedInUser->getDisplayName(), $first_row_title_link->getText());
    $this->assertStringContainsString($this->loggedInUser->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('User', $first_row_type->getText());
  }

  /**
   * Tests operations column.
   *
   * @covers \Drupal\entity_usage\Controller\ListUsageController::getSourceEntityOperations
   */
  public function testOperations(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Create target node.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Target Multi');
    $page->pressButton('Save');
    $assert_session->statusMessageContains('Entity Usage test content Target Multi has been created.', 'status');
    $target_node = $this->drupalGetNodeByTitle('Target Multi', TRUE);

    // Create first source node.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Source A');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Target Multi ({$target_node->id()})");
    $page->pressButton('Save');
    $assert_session->statusMessageContains('Entity Usage test content Source A has been created.', 'status');
    $source_a = $this->drupalGetNodeByTitle('Source A', TRUE);

    // Create second source node.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Source B');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Target Multi ({$target_node->id()})");
    $page->pressButton('Save');
    $assert_session->statusMessageContains('Entity Usage test content Source B has been created.', 'status');
    $source_b = $this->drupalGetNodeByTitle('Source B', TRUE);

    // Visit the usage page.
    $this->drupalGet("/admin/content/entity-usage/node/{$target_node->id()}");

    // Both rows should have their own edit links.
    $row1_operations = $this->assertSession()->elementExists('xpath', '//table/tbody/tr[1]/td[6]');
    $row2_operations = $this->assertSession()->elementExists('xpath', '//table/tbody/tr[2]/td[6]');

    $link1 = $row1_operations->find('css', 'a');
    $link2 = $row2_operations->find('css', 'a');

    // Each link should point to a different source node.
    // Sources are listed newest first.
    $this->assertStringContainsString($source_b->toUrl('edit-form')->toString(), $link1->getAttribute('href'));
    $this->assertStringContainsString($source_a->toUrl('edit-form')->toString(), $link2->getAttribute('href'));

    // If the current request already carries a destination query parameter,
    // the operations links use this listing page as the destination instead
    // of passing that value straight through: otherwise clicking "Edit"
    // would send the user wherever that unrelated destination points,
    // rather than back to this listing.
    $this->drupalGet("/admin/content/entity-usage/node/{$target_node->id()}", [
      'query' => ['destination' => '/some/other/path'],
    ]);
    $row1_operations = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[6]');
    $edit_href = $row1_operations->find('css', 'a')->getAttribute('href');
    $query = [];
    parse_str((string) parse_url($edit_href, PHP_URL_QUERY), $query);
    $this->assertArrayHasKey('destination', $query);
    $this->assertNotEquals('/some/other/path', $query['destination']);
    $this->assertStringEndsWith("/admin/content/entity-usage/node/{$target_node->id()}?destination=/some/other/path", $query['destination']);
  }

  /**
   * Tests operations column when a user does not have access to the operations.
   *
   * @covers \Drupal\entity_usage\Controller\ListUsageController::getSourceEntityOperations
   */
  public function testOperationsWithNoAccess(): void {
    // Enable users to be tracked as source.
    $config = \Drupal::configFactory()->getEditable('entity_usage.settings');
    $config->set('track_enabled_source_entity_types', ['user']);
    $config->save();
    $this->rebuildAll();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $admin_user = $this->drupalCreateUser([
      'administer node fields',
      'administer node display',
      'administer nodes',
      'administer users',
      'bypass node access',
      'use text format eu_test_text_format',
    ]);
    $this->drupalLogin($admin_user);

    // Create a target node.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Target User Source');
    $page->pressButton('Save');
    $assert_session->statusMessageContains('Entity Usage test content Target User Source has been created.', 'status');
    $target_node = $this->drupalGetNodeByTitle('Target User Source', TRUE);

    // Set a reference from the logged-in user to the target node.
    $this->loggedInUser->set('field_eu_test_related_nodes', [
      'target_id' => $target_node->id(),
    ])->save();

    // Visit the usage page.
    $this->drupalGet("/admin/content/entity-usage/node/{$target_node->id()}");
    $assert_session->pageTextContains('Entity usage information for Target User Source');
    $assert_session->elementExists('xpath', '//table/thead//th[text()="Operations"]');
    $ops_cell = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[6]');
    $link = $ops_cell->find('css', 'a');
    $this->assertNotNull($link, 'User entity with administer users shows edit link.');
    $this->assertStringContainsString($this->loggedInUser->toUrl('edit-form')->toString(), $link->getAttribute('href'));

    // Switch to a user that can view usage but cannot view or edit users.
    $restricted_user = $this->drupalCreateUser([
      'access entity usage statistics',
    ]);
    $this->drupalLogin($restricted_user);
    $this->drupalGet("/admin/content/entity-usage/node/{$target_node->id()}");
    $assert_session->pageTextContains('Entity usage information for Target User Source');

    // With no operation available to this user the column is dropped entirely,
    // so that an empty column is not shown.
    $assert_session->elementNotExists('xpath', '//table/thead//th[text()="Operations"]');
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[1]/td[6]');
  }

}
