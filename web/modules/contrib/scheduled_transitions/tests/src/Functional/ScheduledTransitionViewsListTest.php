<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\User;

/**
 * Tests the views global list.
 *
 * The view is automatically enabled when all dependencies are met (views is
 * installed)
 *
 * @group scheduled_transitions
 */
class ScheduledTransitionViewsListTest extends BrowserTestBase {

  use ContentModerationTestTrait;

  protected static $modules = [
    'entity_test',
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'system',
    'filter',
    // Enabling views installs the view.
    'views',
  ];

  protected $defaultTheme = 'stark';

  /**
   * Tests list.
   */
  public function testList(): void {
    // Change long format in config/optional/views.view.scheduled_transitions.
    DateFormat::load('long')?->setPattern('l, j F Y - H:i')->save();

    /** @var \Drupal\user\UserInterface $currentUser */
    $currentUser = $this->drupalCreateUser(['view all scheduled transitions']);
    $this->drupalLogin($currentUser);
    $url = Url::fromRoute('entity.scheduled_transition.collection');

    $workflow = $this->createEditorialWorkflow();
    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_mulrev', 'entity_test_mulrev');
    $workflow->save();

    $entityLabel = $this->randomMachineName();

    $author = User::create([
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestMulRev::create();
    $entity->name = $entityLabel;
    $entity->moderation_state = 'draft';
    $entity->save();
    static::assertEquals(1, $entity->getRevisionId());

    $newState = 'published';
    $date = new \DateTime('2 Feb 2018 11am');
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 1,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => $date->getTimestamp(),
    ]);
    $scheduledTransition->save();

    // Create a transition with no entity.
    ScheduledTransition::create([
      'entity' => NULL,
      'entity_revision_id' => 2,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => $date->getTimestamp(),
    ])->save();

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    $tableRows = $this->cssSelect('table tbody tr');
    static::assertCount(2, $tableRows);

    $row1 = $this->cssSelect('table tbody tr:nth-child(1)');
    $td3 = $row1[0]->find('css', 'td:nth-child(3)');
    static::assertEquals('Draft', $td3->getText());
    $td4 = $row1[0]->find('css', 'td:nth-child(4)');
    static::assertEquals('Published', $td4->getText());
    $td5 = $row1[0]->find('css', 'td:nth-child(5)');
    static::assertEquals('Friday, 2 February 2018 - 11:00', $td5->getText());
  }

}
