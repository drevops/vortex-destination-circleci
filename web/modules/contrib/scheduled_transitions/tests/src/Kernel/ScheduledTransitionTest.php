<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Kernel;

use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\UserSession;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface;
use Drupal\scheduled_transitions_test\Entity\ScheduledTransitionsTestEntity as TestEntity;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\User;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Tests basic functionality of scheduled_transitions fields.
 *
 * @group scheduled_transitions
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsRunner
 */
final class ScheduledTransitionTest extends KernelTestBase {

  use ContentModerationTestTrait;

  private const TEST_LOGGER_SERVICE_NAME = 'test.logger';

  protected static $modules = [
    'entity_test_revlog',
    'entity_test',
    'scheduled_transitions_test',
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'language',
    'system',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('st_entity_test');
    $this->installEntitySchema('st_nont_entity_test');
    $this->installEntitySchema('entity_test_revlog');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installEntitySchema('scheduled_transition');
    $this->installConfig(['scheduled_transitions']);
  }

  /**
   * Tests a scheduled revision.
   *
   * Publish a revision in the past (not latest).
   */
  public function testScheduledRevision(): void {
    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    static::assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    static::assertEquals(2, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    static::assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 2,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => (new \DateTime('2 Feb 2018 11am'))->getTimestamp(),
    ]);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $logs = $this->getLogs();
    static::assertCount(3, $logs);
    static::assertEquals('Copied revision #2 and changed from Draft to Published', $logs[0]['message']);
    static::assertEquals('Processed scheduled transition #1', $logs[1]['message']);
    static::assertEquals('Deleted scheduled transition #1', $logs[2]['message']);

    $revisionIds = $this->getRevisionIds($entity);
    static::assertCount(4, $revisionIds);

    // Reload the entity.
    $entity = EntityTestWithRevisionLog::load($entityId);
    static::assertEquals('published', $entity->moderation_state->value, \sprintf('Entity is now %s.', $newState));
    static::assertEquals('Scheduled transition: copied revision #2 and changed from Draft to Published', $entity->getRevisionLogMessage());

    // Test that revision author is set as per the scheduled transition author.
    static::assertEquals($author->id(), $entity->getRevisionUserId());
  }

  /**
   * Tests a scheduled revision.
   *
   * Publish the latest revision.
   */
  public function testScheduledRevisionLatestNonDefault(): void {
    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    static::assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    static::assertEquals(2, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    static::assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 3,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => (new \DateTime('2 Feb 2018 11am'))->getTimestamp(),
    ]);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $logs = $this->getLogs();
    static::assertCount(3, $logs);
    static::assertEquals('Transitioning latest revision #3 from Draft to Published', $logs[0]['message']);
    static::assertEquals('Processed scheduled transition #1', $logs[1]['message']);
    static::assertEquals('Deleted scheduled transition #1', $logs[2]['message']);

    $revisionIds = $this->getRevisionIds($entity);
    static::assertCount(4, $revisionIds);

    // Reload the entity.
    $entity = EntityTestWithRevisionLog::load($entityId);
    static::assertEquals('published', $entity->moderation_state->value, \sprintf('Entity is now %s.', $newState));
    static::assertEquals('Scheduled transition: transitioning latest revision from Draft to Published', $entity->getRevisionLogMessage());
  }

  /**
   * Tests a scheduled revision.
   */
  public function testScheduledRevisionRecreateNonDefaultHead(): void {
    $workflow = $this->createEditorialWorkflow();
    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->name = 'foobar1';
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    static::assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->name = 'foobar2';
    $entity->moderation_state = 'draft';
    $entity->save();
    static::assertEquals(2, $entity->getRevisionId());

    $revision3State = 'draft';
    $entity->setNewRevision();
    $entity->name = 'foobar3';
    $entity->moderation_state = $revision3State;
    $entity->save();
    static::assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        $newState,
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        $author,
      )
      ->setOptions([
        [ScheduledTransition::OPTION_RECREATE_NON_DEFAULT_HEAD => TRUE],
      ])
      ->setEntityRevisionId(2);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $logs = $this->getLogs();
    static::assertCount(4, $logs);
    static::assertEquals('Copied revision #2 and changed from Draft to Published', $logs[0]['message']);
    static::assertEquals('Reverted Draft revision #3 back to top', $logs[1]['message']);
    static::assertEquals('Processed scheduled transition #1', $logs[2]['message']);
    static::assertEquals('Deleted scheduled transition #1', $logs[3]['message']);

    $revisionIds = $this->getRevisionIds($entity);
    static::assertCount(5, $revisionIds);

    // Reload the entity default revision.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = \Drupal::entityTypeManager()->getStorage('entity_test_revlog');
    $entity = EntityTestWithRevisionLog::load($entityId);
    $revision4 = $entityStorage->loadRevision($revisionIds[3]);
    $revision5 = $entityStorage->loadRevision($revisionIds[4]);
    static::assertEquals($revision4->getRevisionId(), $entity->getRevisionId(), 'Default revision is revision 4');
    static::assertEquals($newState, $entity->moderation_state->value, \sprintf('Entity is now %s.', $newState));

    static::assertEquals($revision4->name->value, 'foobar2');
    static::assertInstanceOf(RevisionLogInterface::class, $revision4);
    static::assertEquals('Scheduled transition: copied revision #2 and changed from Draft to Published', $revision4->getRevisionLogMessage());

    static::assertEquals($revision5->name->value, 'foobar3');
    static::assertInstanceOf(RevisionLogInterface::class, $revision5);
    static::assertEquals('Scheduled transition: reverted Draft revision #3 back to top', $revision5->getRevisionLogMessage());
  }

  /**
   * Tests a scheduled revision.
   *
   * The latest revision is published, ensure it doesn't get republished when
   * recreate_non_default_head is TRUE.
   */
  public function testScheduledRevisionRecreateDefaultHead(): void {
    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->name = 'foobar1';
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    static::assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->name = 'foobar2';
    $entity->moderation_state = 'draft';
    $entity->save();
    static::assertEquals(2, $entity->getRevisionId());

    $revision3State = 'published';
    $entity->setNewRevision();
    $entity->name = 'foobar3';
    $entity->moderation_state = $revision3State;
    $entity->save();
    static::assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        $newState,
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        $author,
      )
      ->setEntityRevisionId(2)
      ->setOptions([
        [ScheduledTransition::OPTION_RECREATE_NON_DEFAULT_HEAD => TRUE],
      ]);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $logs = $this->getLogs();
    static::assertCount(3, $logs);
    static::assertEquals('Copied revision #2 and changed from Draft to Published', $logs[0]['message']);
    static::assertEquals('Processed scheduled transition #1', $logs[1]['message']);
    static::assertEquals('Deleted scheduled transition #1', $logs[2]['message']);

    $revisionIds = $this->getRevisionIds($entity);
    static::assertCount(4, $revisionIds);

    // Reload the entity default revision.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = \Drupal::entityTypeManager()->getStorage('entity_test_revlog');
    $entity = EntityTestWithRevisionLog::load($entityId);
    $revision4 = $entityStorage->loadRevision($revisionIds[3]);
    static::assertEquals($revision4->getRevisionId(), $entity->getRevisionId(), 'Default revision is revision 4');
    static::assertEquals($newState, $entity->moderation_state->value, \sprintf('Entity is now %s.', $newState));

    static::assertEquals($revision4->name->value, 'foobar2');
    static::assertInstanceOf(RevisionLogInterface::class, $revision4);
    static::assertEquals('Scheduled transition: copied revision #2 and changed from Draft to Published', $revision4->getRevisionLogMessage());
  }

  /**
   * Test scheduled transitions are cleaned up when entities are deleted.
   */
  public function testScheduledTransitionEntityCleanUp(): void {
    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $entity = EntityTestWithRevisionLog::create([
      'type' => 'entity_test_revlog',
      'name' => 'foo',
      'moderation_state' => 'draft',
    ]);
    $entity->save();

    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        'published',
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        new UserSession(['uid' => 1]),
      )
      ->setOptions([
        ['recreate_non_default_head' => TRUE],
      ]);
    $scheduledTransition->save();

    $entity->delete();
    static::assertNull(ScheduledTransition::load($scheduledTransition->id()));
  }

  /**
   * Test scheduled transitions are cleaned up when translations are deleted.
   */
  public function testScheduledTransitionEntityTranslationCleanUp(): void {
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $entity = TestEntity::create(['type' => 'st_entity_test']);
    $de = $entity->addTranslation('de');
    $fr = $entity->addTranslation('fr');
    $de->name = 'deName';
    $fr->name = 'frName';
    $de->moderation_state = 'draft';
    $fr->moderation_state = 'draft';
    $entity->save();

    $originalDeRevisionId = $de->getRevisionId();
    $originalFrRevisionId = $fr->getRevisionId();
    static::assertEquals(1, $entity->id());
    static::assertEquals(1, $entity->getRevisionId());
    static::assertEquals(1, $originalDeRevisionId);
    static::assertEquals(1, $originalFrRevisionId);

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();
    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        'published',
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        $author,
      )
      ->setEntityRevisionId($originalDeRevisionId)
      // Transition 'de'.
      ->setEntityRevisionLanguage('de');
    $scheduledTransition->save();
    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        'published',
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        $author,
      )
      ->setEntityRevisionId($originalFrRevisionId)
      // Transition 'fr'.
      ->setEntityRevisionLanguage('fr');
    $scheduledTransition->save();

    $transitions = ScheduledTransition::loadMultiple();
    static::assertCount(2, $transitions);

    // Delete a translation of the entity.
    $entity->removeTranslation('fr');
    $entity->save();

    $transitions = ScheduledTransition::loadMultiple();
    static::assertCount(1, $transitions);

    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $transition */
    $transition = \reset($transitions);
    static::assertEquals('de', $transition->getEntityRevisionLanguage());
  }

  /**
   * Test scheduled transitions are cleaned up when revisions are deleted.
   */
  public function testScheduledTransitionEntityRevisionCleanUp(): void {
    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $entity = EntityTestWithRevisionLog::create([
      'type' => 'entity_test_revlog',
      'name' => 'foo',
      'moderation_state' => 'draft',
    ]);
    $entity->save();

    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        'published',
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        new UserSession(['uid' => 1]),
      )
      ->setOptions([
        ['recreate_non_default_head' => TRUE],
      ]);
    $scheduledTransition->save();

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_revlog');
    $new_revision = $storage->createRevision($entity);
    $new_revision->save();

    // @phpstan-ignore-next-line argument.type
    $storage->deleteRevision($entity->getRevisionId());
    static::assertNull(ScheduledTransition::load($scheduledTransition->id()));
  }

  /**
   * Test when a default or latest revision use a state that no longer exists.
   *
   * Log message displays appropriate info.
   */
  public function testLogsDeletedState(): void {
    $testState1Name = 'foo_default_test_state1';
    $testState2Name = 'foo_non_default_test_state2';
    $testState3Name = 'published';
    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $configuration = $workflow->getTypePlugin()->getConfiguration();
    $configuration['states'][$testState1Name] = [
      'label' => 'Foo',
      'published' => TRUE,
      'default_revision' => TRUE,
      'weight' => 0,
    ];
    $configuration['states'][$testState2Name] = [
      'label' => 'Foo2',
      'published' => TRUE,
      'default_revision' => FALSE,
      'weight' => 0,
    ];
    $workflow->getTypePlugin()->setConfiguration($configuration);
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->name = 'foobar1';
    $entity->moderation_state = $testState1Name;
    $entity->save();
    $entityId = $entity->id();
    static::assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->name = 'foobar3';
    $entity->moderation_state = $testState2Name;
    $entity->save();
    static::assertEquals(2, $entity->getRevisionId());

    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        $testState3Name,
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        $author,
      )
      ->setEntityRevisionId(1)
      ->setOptions([
        [ScheduledTransition::OPTION_RECREATE_NON_DEFAULT_HEAD => TRUE],
      ]);
    $scheduledTransition->save();

    $workflow->getTypePlugin()->deleteState($testState1Name);
    $workflow->getTypePlugin()->deleteState($testState2Name);
    $workflow->save();

    $type = $workflow->getTypePlugin();

    // Transitioning the first revision, will also recreate the pending revision
    // in this workflow because of the OPTION_RECREATE_NON_DEFAULT_HEAD option
    // above.
    $this->runTransition($scheduledTransition);

    $logBuffer = $this->getLogBuffer();
    $logs = $this->getLogs($logBuffer);
    static::assertCount(3, $logs);
    static::assertEquals('Copied revision #1 and changed from - Unknown state - to Published', $logs[0]['message']);
    static::assertEquals('Processed scheduled transition #1', $logs[1]['message']);
    static::assertEquals('Deleted scheduled transition #1', $logs[2]['message']);

    // Also check context of logs, to ensure missing states are present as
    // 'Missing' strings.
    [2 => $context] = $logBuffer[0];
    static::assertEquals('- Unknown state -', $context['@original_state']);
    static::assertEquals('- Unknown state -', $context['@original_latest_state']);
    static::assertEquals('Published', $context['@new_state']);
  }

  /**
   * Tests the moderation state for a specific translation is changed.
   *
   * Other translations remain unaffected.
   */
  public function testTranslationTransition(): void {
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $entity = TestEntity::create(['type' => 'st_entity_test']);
    $de = $entity->addTranslation('de');
    $fr = $entity->addTranslation('fr');
    $de->name = 'deName';
    $fr->name = 'frName';
    $de->moderation_state = 'draft';
    $fr->moderation_state = 'draft';
    $entity->save();

    $originalRevisionId = $entity->getRevisionId();
    $originalDeRevisionId = $de->getRevisionId();
    $originalFrRevisionId = $fr->getRevisionId();
    static::assertEquals(1, $entity->id());
    static::assertEquals(1, $entity->getRevisionId());
    static::assertEquals(1, $originalDeRevisionId);
    static::assertEquals(1, $originalFrRevisionId);

    /** @var \Drupal\user\UserInterface $author */
    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();
    $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        'published',
        $entity,
        new \DateTime('2 Feb 2018 11am'),
        $author,
      )
      ->setEntityRevisionId(1)
      // Transition 'de'.
      ->setEntityRevisionLanguage('de');
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    // Reload entity.
    $entity = TestEntity::load($entity->id());
    // Revision ID increments for all translations.
    // @phpstan-ignore-next-line binaryOp.invalid
    static::assertEquals($originalRevisionId + 1, $entity->getRevisionId());
    // @phpstan-ignore-next-line binaryOp.invalid
    static::assertEquals($originalFrRevisionId + 1, $entity->getTranslation('fr')->getRevisionId());
    // @phpstan-ignore-next-line binaryOp.invalid
    static::assertEquals($originalDeRevisionId + 1, $entity->getTranslation('de')->getRevisionId());
    static::assertEquals('draft', $entity->moderation_state->value);
    static::assertEquals('draft', $entity->getTranslation('fr')->moderation_state->value);
    // Only 'de' is published.
    static::assertEquals('published', $entity->getTranslation('de')->moderation_state->value);
  }

  /**
   * Tests no pending revisions after transition on revision w/no field changes.
   *
   * After creating a revision, then publishing the entity, create a non default
   * revision, without changing any fields. Then schedule this revision to be
   * published. Afterwards, the entity should have no more 'pending' revisions
   * according to Content Moderation. This pending flag ensures the
   * 'Latest revision' tab no longer shows up in the UI.
   */
  public function testTransitionNoFieldChanges(): void {
    /** @var \Drupal\user\UserInterface $author */
    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    /** @var \Drupal\Core\Entity\TranslatableRevisionableStorageInterface $entityStorage */
    $entityStorage = \Drupal::entityTypeManager()->getStorage('st_entity_test');

    $entity = TestEntity::create(['type' => 'st_entity_test']);

    $entity = $entityStorage->createRevision($entity, FALSE);
    $entity->name = 'rev1';
    $entity->moderation_state = 'draft';
    $entity->save();

    $entity = $entityStorage->createRevision($entity, FALSE);
    $entity->name = 'rev2';
    $entity->moderation_state = 'published';
    $entity->save();

    /** @var \Drupal\content_moderation\ModerationInformationInterface $moderationInformation */
    $moderationInformation = \Drupal::service('content_moderation.moderation_information');

    // Do not change any storage fields this time.
    $entity = $entityStorage->createRevision($entity, FALSE);
    $entity->moderation_state = 'draft';
    $entity->save();

    // At this point there should be a pending revision.
    static::assertTrue($moderationInformation->hasPendingRevision($entity));

    $scheduledTransition = ScheduledTransition::createFrom(
      $workflow,
      'published',
      $entity,
      new \DateTime('1 year ago'),
      $author,
    );
    $scheduledTransition->save();
    $this->runTransition($scheduledTransition);

    static::assertFalse($moderationInformation->hasPendingRevision($entity));
  }

  /**
   * Test the changed timestamp is updated when a transition is executed.
   */
  public function testChangedTimeUpdated(): void {
    /** @var \Drupal\user\UserInterface $author */
    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    /** @var \Drupal\Core\Entity\TranslatableRevisionableStorageInterface $entityStorage */
    $entityStorage = \Drupal::entityTypeManager()->getStorage('st_entity_test');

    $entity = TestEntity::create(['type' => 'st_entity_test']);

    $entity = $entityStorage->createRevision($entity, FALSE);
    $entity->name = 'rev1';
    // @phpstan-ignore-next-line assign.propertyType
    $entity->changed = (new \DateTime('1 year ago'))->getTimestamp();
    $entity->moderation_state = 'draft';
    $entity->save();

    $scheduledTransition = ScheduledTransition::createFrom(
      $workflow,
      'published',
      $entity,
      new \DateTime('1 year ago'),
      $author,
    );
    $scheduledTransition->save();
    $this->runTransition($scheduledTransition);

    /** @var \Drupal\Component\Datetime\TimeInterface $time */
    $time = \Drupal::service('datetime.time');
    static::assertEquals($time->getRequestTime(), $entityStorage->load($entity->id())->changed->value);
  }

  /**
   * Tests Scheduled Transition metadata after processing.
   */
  public function testScheduledTransitionStateAfterProcessing(): void {
    /** @var \Drupal\user\UserInterface $author */
    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $workflow = $this->createEditorialWorkflow();

    $plugin = $workflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $entity = TestEntity::create(['type' => 'st_entity_test']);
    $entity->name = 'rev1';
    $entity->save();

    $createScheduledTransition = static function (TestEntity $entity) use ($workflow, $author): ScheduledTransitionInterface {
      $scheduledTransition = ScheduledTransition::createFrom(
        $workflow,
        'published',
        $entity,
        new \DateTime('1 year ago'),
        $author,
      );
      $scheduledTransition->save();
      return $scheduledTransition;
    };

    $scheduledTransition = $createScheduledTransition($entity);
    $this->runTransition($scheduledTransition);
    static::assertNull($scheduledTransition::load($scheduledTransition->id()));

    \Drupal::configFactory()->getEditable('scheduled_transitions.settings')->set('retain_processed', [
      'duration' => -1,
      'enabled' => TRUE,
    ])->save(TRUE);

    // Reload $entity as it has been modified by above.
    $entity = $entity::load($entity->id());
    $scheduledTransition = $createScheduledTransition($entity);
    $this->runTransition($scheduledTransition);
    $scheduledTransition = $scheduledTransition::load($scheduledTransition->id());
    static::assertNotNull($scheduledTransition);
    static::assertTrue($scheduledTransition->isProcessed());
    $entity = $entity::load($entity->id());
    static::assertEquals([$entity->getRevisionId()], $scheduledTransition->getProcessedRevisions());
  }

  /**
   * Checks and runs any ready transitions.
   */
  protected function runTransition(ScheduledTransitionInterface $scheduledTransition): void {
    /** @var \Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface $runner */
    $runner = \Drupal::service(ScheduledTransitionsRunnerInterface::class);
    $runner->runTransition($scheduledTransition);
  }

  /**
   * Gets logs from buffer and cleans out buffer.
   *
   * Reconstructs logs into plain strings.
   *
   * @param array|null $logBuffer
   *   A log buffer from getLogBuffer, or provide an existing value fetched from
   *   getLogBuffer. This is a workaround for the logger clearing values on
   *   call.
   *
   * @return array
   *   Logs from buffer, where values are an array with keys: severity, message.
   */
  protected function getLogs(?array $logBuffer = NULL): array {
    $logs = \array_map(static function (array $log) {
      [$severity, $message, $context] = $log;
      return [
        'severity' => $severity,
        'message' => \str_replace(\array_keys(\array_map(\strval(...), $context)), \array_values($context), $message),
      ];
    }, $logBuffer ?? $this->getLogBuffer());
    return \array_values($logs);
  }

  /**
   * Gets logs from buffer and cleans out buffer.
   *
   * @array
   *   Logs from buffer, where values are an array with keys: severity, message.
   */
  protected function getLogBuffer(): array {
    /** @var \Symfony\Component\ErrorHandler\BufferingLogger $logger */
    $logger = $this->container->get(static::TEST_LOGGER_SERVICE_NAME);
    return $logger->cleanLogs();
  }

  /**
   * Get revision IDs for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   *
   * @return int[]
   *   Revision IDs.
   */
  protected function getRevisionIds(EntityInterface $entity): array {
    $entityTypeId = $entity->getEntityTypeId();
    $entityDefinition = \Drupal::entityTypeManager()->getDefinition($entityTypeId);
    $entityStorage = \Drupal::entityTypeManager()->getStorage($entityTypeId);

    /** @var int[] $ids */
    $ids = $entityStorage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition((string) $entityDefinition->getKey('id'), $entity->id())
      ->execute();
    return \array_keys($ids);
  }

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register(static::TEST_LOGGER_SERVICE_NAME, BufferingLogger::class)
      ->addTag('logger');
  }

  protected function tearDown(): void {
    // Clean out logs so their aren't sent out to stderr.
    /** @var \Symfony\Component\ErrorHandler\BufferingLogger $logger */
    $logger = $this->container->get(static::TEST_LOGGER_SERVICE_NAME);
    $logger->cleanLogs();
    parent::tearDown();
  }

}
