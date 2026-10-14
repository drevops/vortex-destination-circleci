<?php

declare(strict_types=1);

namespace Drupal\Tests\environment_indicator\Functional\Form;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the environment indicator form behavior.
 *
 * @group environment_indicator
 */
class EnvironmentIndicatorFormTest extends BrowserTestBase {

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
   * A user with permission to administer environment indicators.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer environment indicator settings',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the normalization of the base URL.
   *
   * @dataProvider baseUrlProvider
   */
  public function testBaseUrlNormalization(string $inputUrl, string $expectedUrl): void {
    $this->drupalGet('/admin/config/development/environment-indicator/switcher/add');

    $edit = [
      'machine' => 'test_' . preg_replace('/[^a-z]/', '_', strtolower(parse_url($inputUrl, PHP_URL_HOST) ?? 'url')),
      'name' => 'Test Env',
      'url' => $inputUrl,
      'fg_color' => '#ffffff',
      'bg_color' => '#000000',
    ];
    $this->submitForm($edit, 'Save');

    $machine_name = $edit['machine'];
    $config = $this->config("environment_indicator.switcher.$machine_name");
    $this->assertSame($expectedUrl, $config->get('url'), "URL '$inputUrl' normalized to '$expectedUrl'.");
  }

  /**
   * Provides test data for the base URL.
   *
   * @dataProvider baseUrlProvider
   */
  public static function baseUrlProvider(): array {
    return [
      'no trailing slash' => ['https://example.com', 'https://example.com'],
      'single trailing slash' => ['https://example.com/', 'https://example.com'],
      'double trailing slashes' => ['https://example.com//', 'https://example.com'],
      'trailing slash with path' => ['https://example.com/foo/', 'https://example.com/foo'],
      'no slash with path' => ['https://example.com/foo', 'https://example.com/foo'],
      'trailing slash with query' => ['https://example.com/foo/?bar=baz', 'https://example.com/foo/?bar=baz'],
      'no trailing slash with query' => ['https://example.com/foo?bar=baz', 'https://example.com/foo?bar=baz'],
    ];
  }

}
