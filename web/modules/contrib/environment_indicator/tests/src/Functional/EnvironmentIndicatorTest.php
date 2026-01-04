<?php

declare(strict_types=1);

namespace Drupal\Tests\environment_indicator\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for Environment Indicator.
 *
 * @group environment_indicator
 */
class EnvironmentIndicatorTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'environment_indicator',
    'environment_indicator_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to see the environment indicator.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $privilegedUser;

  /**
   * A user without permission to see the environment indicator.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $unprivilegedUser;

  /**
   * A user with permission to create environment switchers.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $environmentIndicatorAdministrator;

  /**
   * The path to the environment_indicator module.
   *
   * @var string
   */
  protected string $modulePath;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Retrieve the dynamic module path.
    $moduleHandler = \Drupal::getContainer()->get('extension.list.module');
    $this->modulePath = $moduleHandler->getPath('environment_indicator');
    $settings = $this->config('environment_indicator.settings');
    $settings->set('toolbar_integration', [])->save();
    $this->state = $this->container->get('state');
    $this->resetAll();
    // Create users.
    $this->privilegedUser = $this->drupalCreateUser(['access environment indicator']);
    $this->unprivilegedUser = $this->drupalCreateUser();
  }

  /**
   * Tests that the environment indicator appears in the page top region.
   *
   * This test verifies that the environment indicator appears in the page top
   * region with the expected attributes.
   */
  public function testVisibilityWithPermissions(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Red Green Environment')
      ->set('fg_color', 'green')
      ->set('bg_color', 'red')
      ->save();
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->resetAll();
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->pageTextContains('Red Green Environment');
    $session->elementExists('css', '#environment-indicator');
    $element = $this->getSession()->getPage()->find('css', '#environment-indicator');
    $this->assertNotNull($element, 'Environment indicator element found.');
    $this->assertNotEmpty($element->getAttribute('style'), 'Style attribute should not be empty.');
    $this->assertStringContainsString('background-color: red', $element->getAttribute('style'));
    $this->assertStringContainsString('color: green', $element->getAttribute('style'));
    $this->assertSession()->elementExists("css", "link[href*='{$this->modulePath}/css/environment_indicator.css']");
    // Change configuration values.
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Test Environment')
      ->set('fg_color', '#ffffff')
      ->set('bg_color', '#000000')
      ->save();
    // Clear drupal cache.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->resetAll();
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->pageTextContains('Test Environment');
    $session->elementExists('css', '#environment-indicator');
    $element = $this->getSession()->getPage()->find('css', '#environment-indicator');
    $this->assertNotNull($element, 'Environment indicator element found.');
    $this->assertNotEmpty($element->getAttribute('style'), 'Style attribute should not be empty.');
    $this->assertStringContainsString('background-color: #000000', $element->getAttribute('style'));
    $this->assertStringContainsString('color: #ffffff', $element->getAttribute('style'));

    // Change configuration values.
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Development Environment')
      ->set('fg_color', '#efefef')
      ->set('bg_color', '#12285f')
      ->save();
    // Clear drupal cache.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->resetAll();
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    // Assert that the environment indicator exists with
    // the expected attributes.
    $session->elementExists('css', '#environment-indicator');
    $element = $this->getSession()->getPage()->find('css', '#environment-indicator');
    $this->assertNotNull($element, 'Environment indicator element found.');
    $this->assertNotEmpty($element->getAttribute('style'), 'Style attribute should not be empty.');
    $this->assertStringContainsString('background-color: #12285f', $element->getAttribute('style'));
    $this->assertStringContainsString('color: #efefef', $element->getAttribute('style'));
    $session->pageTextContains('Development Environment');
  }

  /**
   * Tests visibility for unauthorized users.
   */
  public function testVisibilityWithoutPermissions(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Test Environment')
      ->set('fg_color', '#000000')
      ->set('bg_color', '#ffffff')
      ->save();
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->resetAll();
    $this->drupalLogin($this->drupalCreateUser());
    $this->drupalGet('<front>');
    $this->assertSession()->elementNotExists('css', '#environment-indicator');
    $this->assertSession()->elementNotExists("css", "link[href*='{$this->modulePath}/css/environment_indicator.css']");
  }

  /**
   * Tests the indicator with the default configuration.
   */
  public function testIndicatorDefaultConfiguration(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Indicator Environment')
      ->set('fg_color', '#000000')
      ->set('bg_color', '#ffffff')
      ->save();

    $this->state->set('environment_indicator.current_release', 'v1.2.44');
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);

    $this->resetAll();
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');

    $element = $this->getSession()->getPage()->find('css', '#environment-indicator');
    $this->assertNotNull($element, 'Environment indicator element found.');
    $this->assertNotEmpty($element->getAttribute('style'), 'Style attribute should not be empty.');
    $this->assertStringContainsString('background-color: #ffffff', $element->getAttribute('style'));
    $this->assertStringContainsString('color: #000000', $element->getAttribute('style'));
    $this->assertSession()->elementExists('css', 'div#environment-indicator span.description');
    $this->assertSession()->pageTextContains('v1.2.44');
  }

  /**
   * Tests that the environment indicator does not show when the name is empty.
   */
  public function testNoIndicatorWhenNameIsEmpty(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', '')
      ->set('fg_color', '#000000')
      ->set('bg_color', '#ffffff')
      ->save();
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');

    $this->assertSession()->elementNotExists('css', '#environment-indicator');
  }

  /**
   * Tests that an integer in state does not break the environment indicator.
   */
  public function testIntegerInState(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'State Integer Test')
      ->set('fg_color', '#123456')
      ->set('bg_color', '#abcdef')
      ->save();

    // Set an integer version identifier.
    $this->state->set('environment_indicator.current_release', 12345);

    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->resetAll();

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');

    $element = $this->getSession()->getPage()->find('css', '#environment-indicator');
    $this->assertNotNull($element, 'Environment indicator element not found.');
    $this->assertStringContainsString('background-color: #abcdef', $element->getAttribute('style'));
    $this->assertStringContainsString('color: #123456', $element->getAttribute('style'));

    // The integer should be rendered as text in the indicator output.
    $this->assertSession()->pageTextContains('12345');
  }

}
