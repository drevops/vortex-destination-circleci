<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\entity_usage\EntityUsageBatchManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\user\Entity\Role;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the batch update functionality.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
#[Group('entity_usage')]
#[RunTestsInSeparateProcesses]
class BatchUpdateTest extends EntityUsageJavascriptTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   * Tests the batch update.
   */
  public function testBatchUpdate(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // No permissions, you get a 403 when trying to access the batch update.
    $this->drupalGet('/admin/config/entity-usage/batch-update');
    $assert_session->pageTextContains('You are not authorized to access this page');
    // Grant the logged-in the needed permission and try again.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, ['perform batch updates entity usage']);
    $this->drupalGet('/admin/config/entity-usage/batch-update');
    $assert_session->pageTextContains('Batch update');
    $assert_session->pageTextContains('This page allows you to delete and re-generate again all entity usage statistics in your system');

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    $node1 = Node::load(1);

    // Create node 2 referencing node 1 using reference field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', 'Node 1 (1)');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been created.');

    // Create node 3 also referencing node 1 in a reference field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 3');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', 'Node 1 (1)');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been created.');

    // Remove one of the records from the database to simulate an usage
    // non-tracked by the module.
    $usage_service->deleteBySourceEntity(2, 'node');
    $usage = $usage_service->listSources($node1);
    $this->assertEquals($usage['node'], [
      '3' => [
        0 => [
          'source_langcode' => 'en',
          'source_vid' => '3',
          'method' => 'entity_reference',
          'field_name' => 'field_eu_test_related_nodes',
          'count' => 1,
        ],
      ],
    ]);

    // Go to the batch update page and check the update.
    $this->drupalGet('/admin/config/entity-usage/batch-update');
    $assert_session->pageTextContains('Batch Update');
    $assert_session->pageTextContains('This page allows you to delete and re-generate again all entity usage statistics in your system.');
    $assert_session->pageTextContains('You may want to check the settings page to fine-tune what entities should be tracked, and other options.');

    // If in the settings form we have disabled tracking for nodes, the batch
    // update should remove the usages.
    $config = \Drupal::configFactory()->getEditable('entity_usage.settings');
    $config->set('track_enabled_source_entity_types', []);
    $config->save();

    // Set the event recorder to empty so we can ensure no events are triggered.
    \Drupal::keyValue('entity_usage_test')->set('register', []);

    $page->pressButton('Recreate all entity usage statistics');
    $assert_session->waitForText('Recreated entity usage for');
    $assert_session->pageTextContains('Recreated entity usage for');
    $this->saveHtmlOutput();

    $usage = $usage_service->listSources($node1);
    $this->assertEmpty($usage);
    $this->assertEmpty(\Drupal::keyValue('entity_usage_test')->get('register', []));

    // Create a bulk table to test that we don't error in this situation.
    $context = [];
    EntityUsageBatchManager::createBulkTable($context);
    $this->assertTrue(\Drupal::database()->schema()->tableExists(EntityUsageBatchManager::BULK_TABLE_NAME), 'Entity usage bulk table has been created.');

    // Enable tracking for source nodes and try again.
    $config = \Drupal::configFactory()->getEditable('entity_usage.settings');
    $config->set('track_enabled_source_entity_types', ['node']);
    $config->set('track_enabled_target_entity_types', ['node', 'node_type']);
    $config->set('track_enabled_base_fields', TRUE);
    $config->save();
    $this->drupalGet('/admin/config/entity-usage/batch-update');
    $page->pressButton('Recreate all entity usage statistics');
    $assert_session->waitForText('Recreated entity usage for');
    $this->htmlOutput();
    $assert_session->pageTextContains('Recreated entity usage for');

    // Check if the resulting usage is the expected.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals($usage['node'], [
      '3' => [
        0 => [
          'source_langcode' => 'en',
          'source_vid' => '3',
          'method' => 'entity_reference',
          'field_name' => 'field_eu_test_related_nodes',
          'count' => 1,
        ],
      ],
      '2' => [
        0 => [
          'source_langcode' => 'en',
          'source_vid' => '2',
          'method' => 'entity_reference',
          'field_name' => 'field_eu_test_related_nodes',
          'count' => 1,
        ],
      ],
    ]);
    /** @var \Drupal\entity_usage\Events\EntityUsageEvent[] $events */
    $events = \Drupal::keyValue('entity_usage_test')->get('register', []);
    $this->assertCount(5, $events);
    // Test a target with a string ID.
    $this->assertSame(1, $events[0]->getCount());
    $this->assertSame('entity_reference', $events[0]->getMethod());
    $this->assertSame('type', $events[0]->getFieldName());
    $this->assertSame('1', $events[0]->getSourceEntityId());
    $this->assertSame(1, $events[0]->getSourceEntityRevisionId());
    $this->assertSame('node', $events[0]->getSourceEntityType());
    $this->assertSame('en', $events[0]->getSourceEntityLangcode());
    $this->assertSame('eu_test_ct', $events[0]->getTargetEntityId());
    $this->assertSame('node_type', $events[0]->getTargetEntityType());
    // Test a target with an integer ID.
    $this->assertSame(1, $events[2]->getCount());
    $this->assertSame('entity_reference', $events[2]->getMethod());
    $this->assertSame('field_eu_test_related_nodes', $events[2]->getFieldName());
    $this->assertSame('2', $events[2]->getSourceEntityId());
    $this->assertSame(2, $events[2]->getSourceEntityRevisionId());
    $this->assertSame('node', $events[2]->getSourceEntityType());
    $this->assertSame('en', $events[2]->getSourceEntityLangcode());
    $this->assertSame('1', $events[2]->getTargetEntityId());
    $this->assertSame('node', $events[2]->getTargetEntityType());

    $this->assertFalse(\Drupal::database()->schema()->tableExists(EntityUsageBatchManager::BULK_TABLE_NAME), 'Entity usage bulk table has been removed.');

    // Ensure the drush command works too.
    $this->drush('entity-usage:recreate', ['-vvv'], ['uri' => $this->baseUrl]);
    $this->assertStringContainsString('[notice] Truncated the entity usage table', $this->getErrorOutput());
    $this->assertStringNotContainsString('[notice] Updating entity usage for node: 1 of 3', $this->getErrorOutput());
    $this->assertStringContainsString('[notice] Message: Recreated entity usage for 3 entities.', $this->getErrorOutput());

    $this->drush('entity-usage:recreate', ['-vvv', '--keep-existing-records'], ['uri' => $this->baseUrl]);
    $this->assertStringNotContainsString('[notice] Truncated the entity usage table', $this->getErrorOutput());
    $this->assertStringContainsString('[notice] Updating entity usage for node: 1 of 3', $this->getErrorOutput());
    $this->assertStringContainsString('[notice] Message: Recreated entity usage for 3 entities.', $this->getErrorOutput());
  }

  /**
   * Tests that the bulk revisionable batch does not persistently cache.
   */
  public function testBatchUpdateDoesNotPersistentlyCacheRevisions(): void {
    // Add a paragraph field to the content type so that bulk loading node
    // revisions also transitively loads paragraph revisions.
    ParagraphsType::create(['id' => 'eu_test_para', 'label' => 'EU Test Para'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_eu_test_para_target',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_eu_test_para_target',
      'entity_type' => 'paragraph',
      'bundle' => 'eu_test_para',
      'label' => 'Target',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_eu_test_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_eu_test_paragraphs',
      'entity_type' => 'node',
      'bundle' => 'eu_test_ct',
      'label' => 'Paragraphs',
      'settings' => ['handler' => 'default:paragraph'],
    ])->save();

    // A target for the paragraph to reference, so its usage is tracked (and
    // therefore the paragraph revision is actually loaded while tracking).
    $target = Node::create(['type' => 'eu_test_ct', 'title' => 'Paragraph target']);
    $target->save();

    $paragraph = Paragraph::create([
      'type' => 'eu_test_para',
      'field_eu_test_para_target' => ['target_id' => $target->id()],
    ]);
    $paragraph->save();
    $paragraph_revision_ids = [(int) $paragraph->getRevisionId()];

    // Create a node with several revisions, each referencing a new revision
    // of the paragraph, so the bulk revisionable code path loads more than
    // one node revision, and transitively more than one paragraph revision.
    $node = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Revisioned node',
      'field_eu_test_paragraphs' => $paragraph,
    ]);
    $node->save();
    $revision_ids = [(int) $node->getRevisionId()];
    for ($i = 0; $i < 2; $i++) {
      // Paragraphs are composite entities: saving the host with a new
      // revision automatically creates a new paragraph revision too, kept
      // in sync on the same (still referenced) $paragraph object.
      $node->setNewRevision(TRUE);
      $node->save();
      $revision_ids[] = (int) $node->getRevisionId();
      $paragraph_revision_ids[] = (int) $paragraph->getRevisionId();
    }

    // Clear the persistent entity cache to prove that recreating entity usage
    // does not persistently cache revisions.
    $cache = \Drupal::cache('entity');
    $cache->deleteAll();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->drupalCreateUser(['perform batch updates entity usage']));
    $this->drupalGet('/admin/config/entity-usage/batch-update');
    $page->pressButton('Recreate all entity usage statistics');
    $assert_session->waitForText('Recreated entity usage for');
    $assert_session->pageTextContains('Recreated entity usage for');

    // Confirm the paragraph was actually tracked, and its revisions were
    // therefore actually loaded, not just trivially never referenced.
    $rows = \Drupal::service('entity_usage.usage')->listSources($target)['node'][$node->id()] ?? [];
    $this->assertCount(3, $rows);
    $this->assertSame('entity_reference_revision_field', $rows[0]['method']);
    $this->assertSame('field_eu_test_paragraphs', $rows[0]['field_name']);

    // The node and paragraph revisions loaded to build entity usage records
    // are never needed again, so the bulk update must not have left them in
    // the persistent entity cache.
    foreach ($revision_ids as $revision_id) {
      $this->assertFalse($cache->get('values:node:revision:' . $revision_id), 'Node revision ' . $revision_id . ' was not persistently cached during the bulk update.');
    }
    foreach ($paragraph_revision_ids as $revision_id) {
      $this->assertFalse($cache->get('values:paragraph:revision:' . $revision_id), 'Paragraph revision ' . $revision_id . ' was not persistently cached during the bulk update.');
    }

    // The original cache backend must be restored once the bulk update has
    // finished: loading a revision now should populate the persistent cache
    // as usual.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node_storage->loadRevision($revision_ids[0]);
    $this->assertNotFalse($cache->get('values:node:revision:' . $revision_ids[0]), 'Persistent caching of revisions works normally outside of the bulk update.');
  }

}
