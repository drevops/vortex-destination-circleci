<?php

declare(strict_types=1);

namespace Drupal\Tests\environment_indicator\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the toolbar integration.
 *
 * @group environment_indicator
 */
class ToolbarIntegrationTest extends WebDriverTestBase {

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

    // Create users.
    $this->privilegedUser = $this->drupalCreateUser([
      'administer environment indicator settings',
      'access environment indicator',
      'access administration pages',
      'access toolbar',
    ]);
    $this->unprivilegedUser = $this->drupalCreateUser();
  }

  /**
   * Tests creating and using environment switchers.
   *
   * @group environment_indicator_switchers
   */
  public function testSwitchers(): void {
    // Set up the environment indicator.
    $config = $this->config('environment_indicator.indicator');
    $config->set('name', 'PHPUnit Environment');
    $config->set('bg_color', '#356388');
    $config->set('fg_color', '#39B54A');
    $config->save();
    // Create a few environment switchers.
    $config = $this->config('environment_indicator.switcher.staging');
    $config->set('name', 'Staging Environment');
    $config->set('machine', 'staging');
    $config->set('url', 'https://staging.example.com');
    $config->set('weight', 1);
    $config->set('fg_color', '#000000');
    $config->set('bg_color', '#ADD8E6');
    $config->save();
    $config = $this->config('environment_indicator.switcher.production');
    $config->set('name', 'Production Environment');
    $config->set('machine', 'production');
    $config->set('url', 'https://example.com');
    $config->set('weight', 2);
    $config->set('fg_color', '#FFFFFF');
    $config->set('bg_color', '#FF0000');
    $config->save();
    // Invalidate the cache.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:environment_indicator.indicator']);
    // Log in as a privileged user.
    $this->drupalLogin($this->privilegedUser);
    // Visit the admin section and check if the switcher appears.
    $this->drupalGet('admin');
    // Assert that PHPUnit Environment exists with the expected attributes.
    $session = $this->assertSession();
    $session->pageTextContains('PHPUnit Environment');
    $session->elementNotExists('css', '#environment-indicator');
    $this->assertSession()->pageTextContains('PHPUnit Environment');
    $environment_indicator = $this->getSession()->getPage()->findLink('PHPUnit Environment');
    $environment_indicator->click();
    $this->assertSession()->pageTextContains('Environments menu');
    $switcher = $this->getSession()->getPage()->findLink('Open on Staging Environment');
    $this->assertNotNull($switcher, 'Switcher for Staging Environment exists.');
    $this->assertEquals('https://staging.example.com/web/admin', $switcher->getAttribute('href'));
    $switcher = $this->getSession()->getPage()->findLink('Open on Production Environment');
    $this->assertNotNull($switcher, 'Switcher for Production Environment does not exist.');
    // Verify that the styles are correctly applied.
    $this->assertStringContainsString('background-color: #FF0000', $switcher->getAttribute('style'));
    $this->assertStringContainsString('color: #FFFFFF', $switcher->getAttribute('style'));
    $this->assertEquals('https://example.com/web/admin', $switcher->getAttribute('href'));
  }

}
