<?php

declare(strict_types=1);

namespace Drupal\Tests\fast404\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the html page functionality.
 *
 * @group fast404
 */
class Fast404HtmlPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['fast404', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $language = ConfigurableLanguage::createFromLangcode('fr');
    $language->save();
  }

  /**
   * Tests the html page functionality.
   */
  public function testHtmlPage() {
    // Let fast404 subscribe to NotFoundHttpException.
    $settings['settings']['fast404_not_found_exception'] = (object) [
      'value' => TRUE,
      'required' => TRUE,
    ];

    // Setup html page without translation.
    $settings['settings']['fast404_HTML_error_page'] = (object) [
      'value' => \Drupal::moduleHandler()->getModule('fast404')->getPath() . '/tests/html/en.html',
      'required' => TRUE,
    ];

    $this->writeSettings($settings);

    // Try to access a non-existent page.
    $this->drupalGet('/unknown-page');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('Custom Page Not Found');

    // Setup html page with translation.
    $settings['settings']['fast404_HTML_error_page'] = (object) [
      'value' => [
        'en' => \Drupal::moduleHandler()->getModule('fast404')->getPath() . '/tests/html/en.html',
        'fr' => \Drupal::moduleHandler()->getModule('fast404')->getPath() . '/tests/html/fr.html',
      ],
      'required' => TRUE,
    ];

    $this->writeSettings($settings);

    // Try to access a non-existent page.
    $this->drupalGet('/unknown-page-en');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('Custom Page Not Found');

    // Try to access a non-existent page.
    $this->drupalGet('/fr/unknown-page-fr');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->pageTextContains('Page personnalisée introuvable');

  }

}
