<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Url;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\User;

/**
 * Tests the non-views global list.
 *
 * @group scheduled_transitions
 */
class ScheduledTransitionListTest extends BrowserTestBase {

  use ContentModerationTestTrait;

  protected static $modules = [
    'entity_test_revlog',
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'system',
  ];

  protected $defaultTheme = 'stark';

  /**
   * Tests list.
   */
  public function testList(): void {
    DateFormat::load('medium')?->setPattern('D, j M Y - H:i')->save();

    /** @var \Drupal\user\UserInterface $currentUser */
    $currentUser = $this->drupalCreateUser(['view all scheduled transitions']);
    $this->drupalLogin($currentUser);
    $url = Url::fromRoute('entity.scheduled_transition.collection');

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no scheduled transitions yet.');

    $workflow = $this->createEditorialWorkflow();
    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $entityLabel = $this->randomMachineName();

    $author = User::create([
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
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

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    $tableRows = $this->cssSelect('table tbody tr');
    static::assertCount(1, $tableRows);

    $row1 = $this->cssSelect('table tbody tr:nth-child(1)');
    $td1 = $row1[0]->find('css', 'td:nth-child(1)');
    static::assertEquals($entityLabel, $td1->getText());
    $td2 = $row1[0]->find('css', 'td:nth-child(2)');
    static::assertEquals('Fri, 2 Feb 2018 - 11:00', $td2->getText());
  }

}
