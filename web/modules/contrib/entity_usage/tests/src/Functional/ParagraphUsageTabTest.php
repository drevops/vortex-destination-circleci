<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Functional;

use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\Core\File\FileExists;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\media\Entity\MediaType;
use Drupal\media\Entity\Media;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional test for paragraphs in the entity usage tab.
 *
 * @group entity_usage
 */
#[Group('entity_usage')]
#[RunTestsInSeparateProcesses]
class ParagraphUsageTabTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'system',
    'field',
    'text',
    'filter',
    'path',
    'file',
    'image',
    'media',
    'media_library',
    'views',
    'paragraphs',
    'entity_reference_revisions',
    'content_moderation',
    'workflows',
    'entity_usage',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Configure entity_usage to track nodes and media by default.
    \Drupal::configFactory()->getEditable('entity_usage.settings')
      ->set('track_enabled_target_entity_types', ['media'])
      ->set('track_enabled_source_entity_types', ['node'])
      ->set('track_enabled_plugins', ['entity_reference', 'entity_reference_revision_field'])
      ->set('local_task_enabled_entity_types', ['media'])
      ->save();

    // Create a moderated content type.
    $type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $type->setNewRevision(TRUE);
    $type->save();

    // Enable content moderation workflow on the article content type.
    $workflow = \Drupal::entityTypeManager()->getStorage('workflow')->create([
      'id' => 'editorial_test',
      'label' => 'Editorial Test',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => [
            'label' => 'Draft',
            'published' => FALSE,
            'default_revision' => FALSE,
            'weight' => 0,
          ],
          'published' => [
            'label' => 'Published',
            'published' => TRUE,
            'default_revision' => TRUE,
            'weight' => 1,
          ],
          'archived' => [
            'label' => 'Archived',
            'published' => FALSE,
            'default_revision' => TRUE,
            'weight' => 1,
          ],
        ],
        'transitions' => [
          'create_new_draft' => [
            'label' => 'Create New Draft',
            'to' => 'draft',
            'weight' => 0,
            'from' => [
              'draft',
              'published',
              'archived',
            ],
          ],
          'publish' => [
            'label' => 'Publish',
            'to' => 'published',
            'weight' => 1,
            'from' => [
              'draft',
              'published',
              'archived',
            ],
          ],
          'unpublish' => [
            'label' => 'Unpublish',
            'to' => 'archived',
            'weight' => 1,
            'from' => [
              'draft',
              'published',
            ],
          ],
        ],
      ],
      'dependencies' => [],
    ]);
    $workflow->save();
    $workflow_plugin = $workflow->getTypePlugin();
    $this->assertInstanceOf(ContentModerationInterface::class, $workflow_plugin);
    $workflow_plugin->addEntityTypeAndBundle('node', 'article');
    $workflow->save();

    // Add a paragraph type with a media reference field.
    ParagraphsType::create(['id' => 'para', 'label' => 'Para'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_media',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_media',
      'entity_type' => 'paragraph',
      'bundle' => 'para',
      'label' => 'Media',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => NULL,
        ],
      ],
    ])->save();

    // Create a node field to hold paragraphs.
    FieldStorageConfig::create([
      'field_name' => 'field_paras',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_paras',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Paragraphs',
      'settings' => [
        'handler' => 'default:paragraph',
      ],
    ])->save();

    // Add an image media reference field directly on the article content type.
    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Image',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => NULL,
        ],
      ],
    ])->save();

    // Create a simple image media type.
    if (!MediaType::load('image')) {
      $media_type = MediaType::create([
        'id' => 'image',
        'label' => 'Image',
        'source' => 'image',
      ]);
      $media_type->save();
      // Create the source field.
      $source_field = $media_type->getSource()->createSourceField($media_type);
      $source_field_storage = $source_field->getFieldStorageDefinition();
      $this->assertInstanceOf(FieldStorageConfigInterface::class, $source_field_storage);
      $source_field_storage->save();
      $source_field->save();
      $media_type
        ->set('source_configuration', [
          'source_field' => $source_field->getName(),
        ])
        ->save();
    }

    // Ensure form displays show the relevant fields.
    $repository = \Drupal::service('entity_display.repository');

    // Node form display for article should show the paragraphs and image
    // fields.
    $node_form_display = $repository->getFormDisplay('node', 'article', 'default');
    $node_form_display->setComponent('field_paras', [
      'type' => 'paragraphs',
    ]);
    $node_form_display->setComponent('field_image', [
      'type' => 'options_select',
    ]);
    $node_form_display->save();

    // Paragraph form display for 'para' should show the media field.
    $para_form_display = $repository->getFormDisplay('paragraph', 'para', 'default');
    $para_form_display->setComponent('field_media', [
      'type' => 'options_select',
    ]);
    $para_form_display->save();

    // Rebuild the router once.
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests entity usage with paragraphs.
   */
  public function testParagraphEntityUsage(): void {
    $this->drupalLogin($this->drupalCreateUser(admin: TRUE));
    $page = $this->getSession()->getPage();

    // Create a file and media entity (target).
    $data = file_get_contents($this->root . '/core/tests/fixtures/files/image-1.png');
    $file = \Drupal::service('file.repository')
      ->writeData($data, 'public://example.png', FileExists::Replace);
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'M1',
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'alt',
      ],
    ]);
    $media->save();

    // Create a paragraph referencing the media.
    $node = $this->editNode(NULL, 'published', para_media: $media);

    // After publishing, the usage should be in the published revision.
    $this->drupalGet($media->toUrl()->toString() . '/usage');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[1]', $node->label());
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[2]', 'Content: Article');
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[5]', 'Published revision');

    // Create a draft node with the paragraph referencing the media.
    $node = $this->editNode($node, 'draft', para_media: $media);
    $this->drupalGet($media->toUrl()->toString() . '/usage');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[1]', $node->label());
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[2]', 'Content: Article');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[5]', 'Published revision');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[5]', 'Draft revision');

    // Publish a revision that removes the reference. This should leave only
    // past revisions, which are hidden by default.
    $node = $this->editNode($node, 'published');
    $this->drupalGet($media->toUrl()->toString() . '/usage');
    $this->assertSession()->pageTextContains('There are no recorded usages');

    // Checking "Show usage in old revisions" brings it back.
    $page->checkField('Show usage in old revisions');
    $page->pressButton('Apply');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[1]', $node->label());
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[2]', 'Content: Article');
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[5]', '2 old revisions');

    // Remove the node.
    $node->delete();

    $this->runDeleteOrphansForm();

    // Create a paragraph referencing the media.
    $node2 = $this->editNode(NULL, 'draft', para_media: $media);
    $this->drupalGet($media->toUrl()->toString() . '/usage');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[1]', $node2->label());
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[2]', 'Content: Article');
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[5]', 'Draft revision');

    // Test deleting a paragraph.
    $this->drupalGet('/node/' . $node2->id() . '/edit');
    $node_title = $this->assertSession()->fieldExists('title[0][value]')->getValue();
    $this->assertSession()->buttonExists('Remove')->press();
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Article ' . $node_title . ' has been updated.');
    $node2 = $this->drupalGetNodeByTitle($node_title, TRUE);

    // With no current usage left at all, the row disappears by default.
    $this->drupalGet($media->toUrl()->toString() . '/usage');
    $this->assertSession()->pageTextContains('There are no recorded usages');

    // It reappears, with the old-revision detail, once requested.
    $page->checkField('Show usage in old revisions');
    $page->pressButton('Apply');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[1]', $node2->label());
    $this->assertSession()->elementTextEquals('xpath', '//table/tbody/tr[1]/td[2]', 'Content: Article');
    $this->assertSession()->elementTextContains('xpath', '//table/tbody/tr[1]/td[5]', '1 old revision');
  }

  /**
   * Edits or creates a node via the UI.
   *
   * @param \Drupal\node\NodeInterface|null $node
   *   The node to edit, set to NULL to create a new node.
   * @param string $status
   *   The status of the node, either 'published' or 'draft'.
   * @param \Drupal\media\MediaInterface|null $node_media
   *   The media entity to add to the node, set to NULL to remove the media.
   * @param \Drupal\media\MediaInterface|null $para_media
   *   The media entity to add to the paragraph, set to NULL to remove the
   *   media.
   *
   * @return \Drupal\node\NodeInterface
   *   The node that was edited or created.
   */
  private function editNode(?NodeInterface $node, string $status, ?MediaInterface $node_media = NULL, ?MediaInterface $para_media = NULL): NodeInterface {
    if (!$node) {
      $this->drupalGet('/node/add/article');
      $node_title = $this->randomString();
      $this->assertSession()
        ->fieldExists('title[0][value]')
        ->setValue($node_title);
    }
    else {
      $this->drupalGet('/node/' . $node->id() . '/edit');
      $node_title = $this->assertSession()
        ->fieldExists('title[0][value]')
        ->getValue();
    }
    $this->assertSession()
      ->fieldExists('moderation_state[0][state]')
      ->selectOption($status);
    $this->assertSession()
      ->fieldExists('field_image')
      ->setValue($node_media?->id() ?? '_none');
    $this->assertSession()
      ->fieldExists('field_paras[0][subform][field_media]')
      ->setValue($para_media?->id() ?? '_none');
    $this->submitForm([], 'Save');
    if (!$node) {
      $this->assertSession()
        ->pageTextContains('Article ' . $node_title . ' has been created.');
    }
    else {
      $this->assertSession()
        ->pageTextContains('Article ' . $node_title . ' has been updated.');
    }
    return $this->drupalGetNodeByTitle($node_title, TRUE);
  }

  /**
   * Programmatically runs the 'Delete orphaned composite entities' form.
   */
  private function runDeleteOrphansForm(): void {
    $this->drupalGet('admin/config/system/delete-orphans');
    $this->submitForm([], 'Delete orphaned composite revisions');
    $this->checkForMetaRefresh();
  }

}
