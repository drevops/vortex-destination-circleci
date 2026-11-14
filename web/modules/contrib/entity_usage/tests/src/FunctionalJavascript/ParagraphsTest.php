<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test integration with paragraphs.
 *
 * @group entity_usage
 */
#[Group('entity_usage')]
#[RunTestsInSeparateProcesses]
class ParagraphsTest extends EntityUsageJavascriptTestBase {

  use ParagraphsTestBaseTrait;
  use MediaTypeCreationTrait;
  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'media',
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   * Tests the integration with paragraphs.
   */
  public function testParagraphsUsage(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Create a media type and some media entities.
    $media_type = $this->createMediaType('image');
    $media1 = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Media asset 1',
    ]);
    $media1->save();

    // Add a Paragraph type that has a single media field.
    $this->addParagraphsType('single_media');
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_media_assets',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'single_media',
      'entity_type' => 'paragraph',
      'field_name' => 'field_media_assets',
      'label' => 'Media assets',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            $media_type->id() => $media_type->id(),
          ],
          'auto_create' => FALSE,
        ],
      ],
    ])->save();
    // Define our widget and formatter for this field.
    \Drupal::service('entity_display.repository')->getFormDisplay('paragraph', 'single_media', 'default')
      ->setComponent('field_media_assets', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('paragraph', 'single_media', 'default')
      ->setComponent('field_media_assets', [
        'type' => 'entity_reference_label',
      ])
      ->save();

    // Add a Paragraph type that has a nested paragraph.
    $this->addParagraphsType('rich_media');
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_nested_paragraphs',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'rich_media',
      'entity_type' => 'paragraph',
      'field_name' => 'field_nested_paragraphs',
      'label' => 'Nested paragraphs',
      'settings' => [],
    ])->save();
    // Define our widget and formatter for this field.
    \Drupal::service('entity_display.repository')->getFormDisplay('paragraph', 'rich_media', 'default')
      ->setComponent('field_nested_paragraphs', [
        'type' => 'paragraphs',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('paragraph', 'rich_media', 'default')
      ->setComponent('field_nested_paragraphs', [
        'type' => 'paragraph_summary',
      ])
      ->save();

    // Add a Content Type with a paragraphs field.
    $this->addParagraphedContentType('paragraphed_test');

    // Add a direct media field to this content type.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_direct_media',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'paragraphed_test',
      'entity_type' => 'node',
      'field_name' => 'field_direct_media',
      'label' => 'Direct media',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            $media_type->id() => $media_type->id(),
          ],
          'auto_create' => FALSE,
        ],
      ],
    ])->save();
    // Define our widget and formatter for this field.
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'paragraphed_test', 'default')
      ->setComponent('field_direct_media', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'paragraphed_test', 'default')
      ->setComponent('field_direct_media', [
        'type' => 'entity_reference_label',
      ])
      ->save();

    // Grant the logged-in user permission to see the statistics page.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, ['access entity usage statistics']);

    // Add a node with some references to media and paragraphs.
    $this->drupalGet('node/add/paragraphed_test');
    $page->fillField('title[0][value]', 'Node 1');
    $arrow_element = $assert_session->elementExists('css', '#edit-field-paragraphs-wrapper span.dropbutton-arrow');
    $arrow_element->click();
    $page->pressButton('Add single_media');
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    // Reference Media 1 from the first-level paragraph.
    $page->fillField('field_paragraphs[0][subform][field_media_assets][0][target_id]', "Media asset 1 ({$media1->id()})");
    $arrow_element->click();
    $page->pressButton('Add rich_media');
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    $nested_arrow_element = $assert_session->elementExists('css', 'div[data-drupal-selector="edit-field-paragraphs-1-subform"] .dropbutton-arrow');
    $nested_arrow_element->click();
    $add_single_media_inside_nested = $assert_session->elementExists('css', 'input[name="field_paragraphs_1_subform_field_nested_paragraphs_single_media_add_more"]');
    $add_single_media_inside_nested->press();
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    // Reference Media 1 again from inside the nested paragraph.
    $page->fillField('field_paragraphs[1][subform][field_nested_paragraphs][0][subform][field_media_assets][0][target_id]', "Media asset 1 ({$media1->id()})");
    // Reference Media 1 directly on the node as well.
    $page->fillField('field_direct_media[0][target_id]', "Media asset 1 ({$media1->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('paragraphed_test Node 1 has been created.');
    /** @var \Drupal\node\NodeInterface $node1 */
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Check the usage page for the media asset is what we expect.
    $this->drupalGet("/admin/content/entity-usage/media/{$media1->id()}");
    $assert_session->pageTextContains('Entity usage information for Media asset 1');
    // There is one row: the paragraph references and the direct reference are
    // counted together.
    $assert_session->elementsCount('xpath', '//table/tbody/tr', 1);
    // The first row contains the direct reference from the host node.
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 1', $first_row_title_link->getText());
    // The link points to the host node.
    $this->assertEquals($node1->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: paragraphed_test', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Direct media', $first_row_field_label->getText());
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published revision', $first_row_status->getText());

    // This saves without creating a new revision. This is an important test of
    // saving without creating a new revision.
    $node1->setUnpublished()->save();
    $this->drupalGet("/admin/content/entity-usage/media/{$media1->id()}");
    $assert_session->elementsCount('xpath', '//table/tbody/tr', 1);
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Draft revision', $first_row_status->getText());
    $node1->setPublished()->save();

    // Remove references to the paragraphs.
    $this->drupalGet("/node/{$node1->id()}/edit");
    // Remove the first paragraph.
    $first_item = $assert_session->elementExists('css', 'div[data-drupal-selector="edit-field-paragraphs-0"]');
    $dropdown = $assert_session->elementExists('css', '.paragraphs-dropdown', $first_item);
    $dropdown->click();
    $this->saveHtmlOutput();
    $remove_button = $assert_session->buttonExists('field_paragraphs_0_remove');
    $remove_button->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    // Remove the second paragraph.
    $second_item = $assert_session->elementExists('css', 'div[data-drupal-selector="edit-field-paragraphs-1"]');
    $dropdown = $assert_session->elementExists('css', '.paragraphs-dropdown', $second_item);
    $dropdown->click();
    $this->saveHtmlOutput();
    $remove_button = $assert_session->buttonExists('field_paragraphs_1_remove');
    $remove_button->press();
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('paragraphed_test Node 1 has been updated.');

    // Assert how orphaned paragraphs on older revisions are shown. The
    // direct reference is still current, so the row stays even with old
    // revisions hidden, but the old-revision detail itself does not.
    $this->drupalGet("/admin/content/entity-usage/media/{$media1->id()}");
    // There is one row: the paragraph references and the direct reference are
    // counted together.
    $assert_session->elementsCount('xpath', '//table/tbody/tr', 1);
    // The first row contains the direct reference from the host node.
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 1', $first_row_title_link->getText());
    $this->assertEquals($node1->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: paragraphed_test', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Direct media', $first_row_field_label->getText());
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published revision', $first_row_status->getText());

    // The old-revision detail is there once explicitly requested. HTMX
    // submits on change, so there is no button to press.
    $page->checkField('Show usage in old revisions');
    $session->wait(500);
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published revision 1 old revision', $first_row_status->getText());

    // Make all the usages only in old revisions.
    $this->drupalGet("/node/{$node1->id()}/edit");
    $page->fillField('field_direct_media[0][target_id]', "");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    // With no current usage left at all, the row disappears by default.
    $this->drupalGet("/admin/content/entity-usage/media/{$media1->id()}");
    $assert_session->pageTextContains('There are no recorded usages');
    $assert_session->pageTextNotContains('Node 1');

    // It reappears, with the old-revision detail, once requested.
    $page->checkField('Show usage in old revisions');
    $session->wait(500);
    $assert_session->elementsCount('xpath', '//table/tbody/tr', 1);
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 1', $first_row_title_link->getText());
    $this->assertEquals($node1->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: paragraphed_test', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Direct media', $first_row_field_label->getText());
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('2 old revisions', $first_row_status->getText());
  }

}
