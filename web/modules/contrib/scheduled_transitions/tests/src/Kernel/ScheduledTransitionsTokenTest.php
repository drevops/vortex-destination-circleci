<?php

declare(strict_types=1);

namespace Drupal\Tests\scheduled_transitions\Kernel;

use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;
use Drupal\KernelTests\KernelTestBase;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsTokenReplacementFactoryInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\User;
use Drupal\workflows\WorkflowInterface;

/**
 * Tests tokens provided by Scheduled Transitions.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsRunner
 * @group scheduled_transitions
 */
class ScheduledTransitionsTokenTest extends KernelTestBase {

  use ContentModerationTestTrait;

  private EntityTestWithRevisionLog $testEntity;
  private WorkflowInterface $testWorkflow;
  private ScheduledTransitionInterface $scheduledTransition;

  protected static $modules = [
    'entity_test_revlog',
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
    $this->installEntitySchema('entity_test_revlog');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installEntitySchema('scheduled_transition');

    $this->testWorkflow = $this->createEditorialWorkflow();
    $plugin = $this->testWorkflow->getTypePlugin();
    static::assertInstanceOf(ContentModerationInterface::class, $plugin);
    $plugin->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $this->testWorkflow->save();

    $this->testEntity = EntityTestWithRevisionLog::create([
      'type' => 'entity_test_revlog',
      'moderation_state' => 'archived',
    ]);
    $this->testEntity->save();
    $this->testEntity->moderation_state = 'draft';
    $this->testEntity->setNewRevision();
    $this->testEntity->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $this->scheduledTransition = ScheduledTransition::create([
      'entity' => $this->testEntity,
      'entity_revision_id' => 1,
      'author' => $author,
      'workflow' => $this->testWorkflow->id(),
      'moderation_state' => 'published',
      'transition_on' => (new \DateTime('-1 day'))->getTimestamp(),
    ]);
    $this->scheduledTransition->save();
  }

  /**
   * Test token replacement object.
   *
   * @covers \Drupal\scheduled_transitions\ScheduledTransitionsTokenReplacements
   */
  public function testTokenReplacement(): void {
    /** @var \Drupal\scheduled_transitions\ScheduledTransitionsTokenReplacementFactoryInterface $tokenReplacementFactory */
    $tokenReplacementFactory = \Drupal::service(ScheduledTransitionsTokenReplacementFactoryInterface::class);

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = \Drupal::entityTypeManager()->getStorage('entity_test_revlog');
    $latest = $entityStorage->loadRevision(1);
    $newRevision = $entityStorage->loadRevision(2);
    $replacement = $tokenReplacementFactory->create($this->scheduledTransition, $newRevision, $latest);
    static::assertEquals([
      'from-revision-id' => '2',
      'from-state' => 'Draft',
      'to-state' => 'Published',
      'latest-revision-id' => '1',
      'latest-state' => 'Archived',
    ], $replacement->getReplacements());
  }

  /**
   * Tests tokens are replaced when a transition is run.
   *
   * Integration test with the runner.
   *
   * @param string $token
   *   The token.
   * @param string $expectedReplacement
   *   What token should be replaced with.
   *
   * @covers ::transitionEntity
   * @covers ::tokenReplace
   * @covers \Drupal\scheduled_transitions\ScheduledTransitionsTokenReplacements
   *
   * @dataProvider providerTokenReplacement
   */
  public function testReplacementWithRunner(string $token, string $expectedReplacement): void {
    \Drupal::configFactory()->getEditable('scheduled_transitions.settings')
      // Add all tokens.
      ->set('message_transition_historical', $token)
      ->save(TRUE);

    $this->runTransition($this->scheduledTransition);

    $entity = $this->testEntity::load($this->testEntity->id());
    static::assertEquals($expectedReplacement, $entity->getRevisionLogMessage());
  }

  /**
   * Provider for testTokenReplacement.
   */
  public static function providerTokenReplacement(): array {
    return [
      'scheduled-transitions:to-state' => [
        '[scheduled-transitions:to-state]',
        'Published',
      ],
      'scheduled-transitions:from-state' => [
        '[scheduled-transitions:from-state]',
        'Archived',
      ],
      'scheduled-transitions:from-revision-id' => [
        '[scheduled-transitions:from-revision-id]',
        '1',
      ],
      'scheduled-transitions:latest-state' => [
        '[scheduled-transitions:latest-state]',
        'Draft',
      ],
      'scheduled-transitions:latest-revision-id' => [
        '[scheduled-transitions:latest-revision-id]',
        '2',
      ],
    ];
  }

  /**
   * Runs transitions.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition.
   */
  protected function runTransition(ScheduledTransitionInterface $scheduledTransition): void {
    /** @var \Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface $runner */
    $runner = \Drupal::service('scheduled_transitions.runner');
    $runner->runTransition($scheduledTransition);
  }

}
