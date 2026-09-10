<?php

declare(strict_types=1);

namespace Drupal\Tests\environment_indicator\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the toolbar integration with the gin theme and gin toolbar.
 *
 * @group environment_indicator
 */
class ToolbarGinIntegrationTest extends WebDriverTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'environment_indicator',
    'environment_indicator_ui',
    'toolbar',
    'gin_toolbar',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Retrieve the dynamic module path.
    $moduleHandler = \Drupal::service('extension.list.module');
    $this->modulePath = $moduleHandler->getPath('environment_indicator');

    // Disable CSS preprocessing.
    $config = $this->config('system.performance');
    $config->set('css.preprocess', FALSE)->save();
    $this->assertTrue(\Drupal::service('theme_installer')->install(['gin']));
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('admin', 'gin')
      ->save();

    // Create users.
    $this->privilegedUser = $this->drupalCreateUser([
      'access environment indicator',
      'access toolbar',
    ]);
    $this->unprivilegedUser = $this->drupalCreateUser();
  }

  /**
   * Tests that the gin classic horizontal toolbar integration works.
   */
  public function testEnvironmentIndicatorGinClassicHorizontal(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Gin Classic Horizontal Environment')
      ->set('fg_color', '#87ff00')
      ->set('bg_color', '#ff0000')
      ->save();
    $gin_config = $this->config('gin.settings');
    $gin_config->set('classic_toolbar', 'horizontal')
      ->save();
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->elementExists('css', "link[href*='{$this->modulePath}/css/environment_indicator.css']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/environment_indicator.js']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/tinycon.min.js']");
    $session->pageTextContains('Gin Classic Horizontal Environment');

  }

  /**
   * Tests that the gin classic vertical toolbar integration works.
   */
  public function testEnvironmentIndicatorGinClassicVertical(): void {
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'Gin Classic Vertical Environment')
      ->set('fg_color', '#87ff00')
      ->set('bg_color', '#ff0000')
      ->save();
    $gin_config = $this->config('gin.settings');
    $gin_config->set('classic_toolbar', 'vertical')
      ->save();
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->elementExists('css', "link[href*='{$this->modulePath}/css/environment_indicator.css']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/environment_indicator.js']");
    $session->elementExists('css', "script[src*='{$this->modulePath}/js/tinycon.min.js']");
    $session->pageTextContains('Gin Classic Vertical Environment');

  }

}
