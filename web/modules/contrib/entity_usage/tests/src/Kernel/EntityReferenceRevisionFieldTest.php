<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\entity_usage\EntityUsageTrackManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_usage\Events\EntityUsageEvent;
use Drupal\entity_usage\Events\Events;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the EntityReferenceRevisionField track plugin.
 *
 * @group entity_usage
 */
#[Group('entity_usage')]
#[RunTestsInSeparateProcesses]
class EntityReferenceRevisionFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'entity_usage',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'entity_usage']);

    NodeType::create(['type' => 'target', 'name' => 'Target'])->save();
    NodeType::create(['type' => 'host', 'name' => 'Host'])->save();
    ParagraphsType::create(['id' => 'para', 'label' => 'Para'])->save();

    // Paragraph holds an entity_reference to a node target.
    FieldStorageConfig::create([
      'field_name' => 'field_target',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_target',
      'entity_type' => 'paragraph',
      'bundle' => 'para',
      'label' => 'Target',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['target' => 'target']],
      ],
    ])->save();

    // Paragraph holds nested paragraphs.
    FieldStorageConfig::create([
      'field_name' => 'field_nested',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_nested',
      'entity_type' => 'paragraph',
      'bundle' => 'para',
      'label' => 'Nested',
      'settings' => ['handler' => 'default:paragraph'],
    ])->save();

    // Host node holds multiple paragraphs.
    FieldStorageConfig::create([
      'field_name' => 'field_paras',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_paras',
      'entity_type' => 'node',
      'bundle' => 'host',
      'label' => 'Paragraphs',
      'settings' => ['handler' => 'default:paragraph'],
    ])->save();

    $this->config('entity_usage.settings')
      ->set('track_enabled_source_entity_types', ['node'])
      ->set('track_enabled_target_entity_types', ['node'])
      ->set('track_enabled_plugins', ['entity_reference'])
      ->save();
  }

  /**
   * Tests two paragraph items referencing the same target register usage once.
   */
  public function testTrackOnEntityCreationDeduplicatesAcrossParagraphs(): void {
    $target = Node::create(['type' => 'target', 'title' => 'Target']);
    $target->save();

    $para1 = Paragraph::create([
      'type' => 'para',
      'field_target' => [['target_id' => $target->id()]],
    ]);
    $para1->save();
    $para2 = Paragraph::create([
      'type' => 'para',
      'field_target' => [['target_id' => $target->id()]],
    ]);
    $para2->save();

    $register_calls = [];
    $listener = function (EntityUsageEvent $event) use ($target, &$register_calls): void {
      if ($event->getTargetEntityId() == $target->id()
        && $event->getTargetEntityType() === 'node'
        && $event->getMethod() === 'entity_reference_revision_field'
      ) {
        $register_calls[] = $event;
      }
    };
    $this->container->get('event_dispatcher')->addListener(Events::USAGE_REGISTER, $listener);

    $host = Node::create([
      'type' => 'host',
      'title' => 'Host',
      'field_paras' => [$para1, $para2],
    ]);
    $host->save();

    $this->assertCount(1, $register_calls, 'registerUsage() is called once for the shared target.');

    $sources = \Drupal::service('entity_usage.usage')->listSources($target);
    $rows = $sources['node'][$host->id()] ?? [];
    $this->assertCount(1, $rows);
    $this->assertSame('entity_reference_revision_field', $rows[0]['method']);
    $this->assertSame('field_paras', $rows[0]['field_name']);
  }

  /**
   * Tests getTargetEntities() finds targets inside nested paragraphs.
   */
  public function testGetTargetEntitiesRecursesIntoNestedParagraphs(): void {
    $target = Node::create(['type' => 'target', 'title' => 'Target']);
    $target->save();

    $inner = Paragraph::create([
      'type' => 'para',
      'field_target' => [['target_id' => $target->id()]],
    ]);
    $inner->save();
    $outer = Paragraph::create([
      'type' => 'para',
      'field_nested' => [$inner],
    ]);
    $outer->save();
    $host = Node::create([
      'type' => 'host',
      'title' => 'Host',
      'field_paras' => [$outer],
    ]);
    $host->save();

    $plugin = \Drupal::service('plugin.manager.entity_usage.track')
      ->createInstance('entity_reference_revision_field');
    $item = $host->get('field_paras')->first();
    $this->assertSame(["node|{$target->id()}"], $plugin->getTargetEntities($item));
  }

  /**
   * Tests inline entities are skipped when no source/target config is defined.
   */
  public function testInlineEntitiesAreSkippedUnderNullConfig(): void {
    // Clear the configured source/target entity type lists so the NULL-fallback
    // branches in EntityUpdateManager run.
    $this->config('entity_usage.settings')
      ->clear('track_enabled_source_entity_types')
      ->clear('track_enabled_target_entity_types')
      ->save();

    $target = Node::create(['type' => 'target', 'title' => 'Target']);
    $target->save();
    $para = Paragraph::create([
      'type' => 'para',
      'field_target' => [['target_id' => $target->id()]],
    ]);
    $para->save();
    $host = Node::create([
      'type' => 'host',
      'title' => 'Host',
      'field_paras' => [$para],
    ]);
    $host->save();

    $usage = \Drupal::service('entity_usage.usage');
    // No row should record the paragraph as a source.
    $sources = $usage->listSources($target);
    $this->assertArrayNotHasKey('paragraph', $sources);
    // The host node should still be tracked as the source.
    $this->assertArrayHasKey('node', $sources);
    $this->assertArrayHasKey($host->id(), $sources['node']);

    // No plugin should record the paragraph as a target either — the ERR
    // plugin walks through paragraphs to find their inner targets instead.
    $targets = $usage->listTargets($host);
    $this->assertArrayNotHasKey('paragraph', $targets);
  }

  /**
   * Re-saving a host with rewired paragraph targets adds and removes rows.
   */
  public function testUpdateTrackingDataForFieldHandlesAddAndRemove(): void {
    $target1 = Node::create(['type' => 'target', 'title' => 'T1']);
    $target1->save();
    $target2 = Node::create(['type' => 'target', 'title' => 'T2']);
    $target2->save();

    $para = Paragraph::create([
      'type' => 'para',
      'field_target' => [['target_id' => $target1->id()]],
    ]);
    $para->save();
    $host = Node::create([
      'type' => 'host',
      'title' => 'Host',
      'field_paras' => [$para],
    ]);
    $host->save();
    $original_vid = (int) $host->getRevisionId();

    $usage = \Drupal::service('entity_usage.usage');
    $this->assertArrayHasKey($host->id(), $usage->listSources($target1)['node']);
    $this->assertArrayNotHasKey('node', $usage->listSources($target2));

    // Change the paragraph to reference target2 and re-save the host on the
    // same revision. updateTrackingDataForField() should add target2 and remove
    // target1.
    $para->set('field_target', [['target_id' => $target2->id()]]);
    $para->save();
    $host->setNewRevision(FALSE);
    $host->save();
    $this->assertSame($original_vid, (int) $host->getRevisionId(), 'Host revision id is unchanged.');

    $this->assertArrayNotHasKey('node', $usage->listSources($target1));
    $this->assertArrayHasKey($host->id(), $usage->listSources($target2)['node']);
  }

  /**
   * Tests listSources() orders by field_name for stable cross-DB output.
   */
  public function testListSourcesOrdersByFieldName(): void {
    $target = Node::create(['type' => 'target', 'title' => 'T']);
    $target->save();
    $host = Node::create(['type' => 'host', 'title' => 'H']);
    $host->save();

    // Register two rows for the same source in non-alphabetical order.
    $usage = \Drupal::service('entity_usage.usage');
    $usage->registerUsage($target->id(), 'node', $host->id(), 'node', 'en', (int) $host->getRevisionId(), 'entity_reference', 'field_zeta');
    $usage->registerUsage($target->id(), 'node', $host->id(), 'node', 'en', (int) $host->getRevisionId(), 'entity_reference', 'field_alpha');

    $rows = $usage->listSources($target)['node'][$host->id()];
    $this->assertCount(2, $rows);
    $this->assertSame(['field_alpha', 'field_zeta'], array_column($rows, 'field_name'));
  }

  /**
   * Tests EntityUsageTrackManager inline helpers.
   */
  public function testTrackManagerInlineHelpers(): void {
    $manager = \Drupal::service('plugin.manager.entity_usage.track');
    assert($manager instanceof EntityUsageTrackManager);

    $this->assertSame(['paragraph'], $manager->getInlineEntityTypeIds());
    $this->assertSame(['entity_reference_revision_field'], $manager->getInlinePluginIds());

    $paragraph = Paragraph::create(['type' => 'para']);
    $node = Node::create(['type' => 'host', 'title' => 'H']);

    // Empty config means "no plugins enabled" — only inline plugins remain.
    $only_inline = $manager->getEnabledPlugins([]);
    $this->assertSame(['entity_reference_revision_field'], array_keys($only_inline));

    // Explicit list is honoured but inline plugins are still added.
    $partial = $manager->getEnabledPlugins(['entity_reference']);
    $this->assertArrayHasKey('entity_reference', $partial);
    $this->assertArrayHasKey('entity_reference_revision_field', $partial);

    // The exclude argument removes the named plugin (used by inline plugins
    // themselves to avoid recursing into their own implementation).
    $excluded = $manager->getEnabledPlugins(['entity_reference'], 'entity_reference_revision_field');
    $this->assertArrayNotHasKey('entity_reference_revision_field', $excluded);
    $this->assertArrayHasKey('entity_reference', $excluded);
  }

}
