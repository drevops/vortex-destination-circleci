<?php

declare(strict_types=1);

namespace Drupal\Tests\environment_indicator\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the toolbar integration.
 *
 * @group environment_indicator
 */
class ToolbarIntegrationTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'environment_indicator',
    'environment_indicator_ui',
    'toolbar',
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
    $moduleHandler = \Drupal::service('extension.list.module');
    $this->modulePath = $moduleHandler->getPath('environment_indicator');
    $this->state = \Drupal::state();

    // Disable CSS preprocessing.
    $config = $this->config('system.performance');
    $config->set('css.preprocess', FALSE)->save();

    // Create users.
    $this->privilegedUser = $this->drupalCreateUser(['access environment indicator', 'access toolbar']);
    $this->unprivilegedUser = $this->drupalCreateUser();
  }

  /**
   * Tests that the element appears in the page top region.
   *
   * If the toolbar module is enabled, and toolbar integration is disabled,
   * the environment indicator should appear in the page top region.
   *
   * This also tests that the correct libraries are loaded.
   */
  public function testEnvironmentIndicatorVisibilityWithToolBarSettingDisabled(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Red Green Environment')
      ->set('fg_color', 'green')
      ->set('bg_color', 'red')
      ->save();
    $settings = $this->config('environment_indicator.settings');
    $settings->set('toolbar_integration', ['toolbar' => 0])
      ->save();
    // Clear drupal cache.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->pageTextContains('Red Green Environment');
    $session->elementExists('css', '#environment-indicator');
    $output = $this->getSession()->getPage()->find('css', '#environment-indicator')->getAttribute('style');
    $this->assertNotEmpty($output, 'Style attribute should not be empty.');
    $this->assertStringContainsString('background-color: red', $output);
    $this->assertStringContainsString('color: green', $output);
    $session->elementExists('css', "link[href*='{$this->modulePath}/css/environment_indicator.css']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/environment_indicator.js']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/tinycon.min.js']");
  }

  /**
   * Tests that the element does not appear in the page top region.
   *
   * If the toolbar module is enabled and the toolbar integration is enabled,
   * the environment indicator should not appear in the page top region.
   *
   * This also tests that the correct libraries are loaded.
   */
  public function testEnvironmentIndicatorVisibilityWithToolBarSettingEnabled(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Test Environment')
      ->set('fg_color', '#000000')
      ->set('bg_color', '#ffffff')
      ->save();
    // Clear drupal cache.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    $this->assertSession()->elementNotExists('css', '#environment-indicator');
    $this->assertSession()->elementExists('css', "link[href*='{$this->modulePath}/css/environment_indicator.css']");
    $this->assertSession()->elementExists('css', "script[src*='{$this->modulePath}/js/environment_indicator.js']");
    $this->assertSession()->elementExists('css', "script[src*='{$this->modulePath}/js/tinycon.min.js']");
  }

  /**
   * Tests that CSS selectors that environment indicator uses exist.
   *
   * We also test that the style tag is present with CSS variables.
   */
  public function testEnvironmentIndicatorToolbarIntegration(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Test Environment')
      ->set('fg_color', '#000000')
      ->set('bg_color', '#ffffff')
      ->save();
    // Clear drupal cache.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->pageTextContains('Test Environment');
    $session->elementNotExists('css', '#environment-indicator');
    // Change configuration values.
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Development Environment')
      ->set('fg_color', '#efefef')
      ->set('bg_color', '#12285f')
      ->save();
    // Clear drupal cache.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->drupalGet('<front>');
    $session->elementExists('css', "link[href*='{$this->modulePath}/css/environment_indicator.css']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/environment_indicator.js']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/tinycon.min.js']");
  }

  /**
   * Tests the indicator with the default configuration.
   */
  public function testIndicatorDefaultConfiguration(): void {
    $this->drupalLogin($this->privilegedUser);
    $this->state->set('environment_indicator.current_release', 'v1.2.44');
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Indicator Environment')
      ->set('fg_color', '#000000')
      ->set('bg_color', '#ffffff')
      ->save();
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('v1.2.44');
  }

}
