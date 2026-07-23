<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel;

use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\KernelTests\Components\ComponentKernelTestBase;
use Drupal\sdc_devel\TwigValidator\TwigValidator;

/**
 * Base class to ease testing of Twig validator rules.
 *
 * @internal
 *
 * phpcs:disable Drupal.Commenting.VariableComment.Missing,
 */
abstract class TwigValidatorTestBase extends ComponentKernelTestBase {

  protected static $modules = [
    'system',
    'sdc_devel',
  ];

  protected static $themes = ['sdc_devel_theme_test'];

  protected TwigValidator $twigValidator;

  protected ComponentPluginManager $componentPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('system');
    $this->twigValidator = \Drupal::service('sdc_devel.twig_validator');
    $this->componentPluginManager = \Drupal::service('plugin.manager.sdc');
  }

  /**
   * Helper to run the twig validator rules tests.
   *
   * @param string $source
   *   The component source.
   * @param array $expected
   *   The errors expected.
   * @param bool $debug
   *   Debug the test itself, print the generated errors.
   */
  public function runTestSourceTwigValidator(string $source, array $expected, bool $debug = FALSE): void {
    $debug_list = $debug_output = [];

    $this->twigValidator->validateSource($source);
    $errors = $this->twigValidator->getMessagesSortedByGroupAndLine();

    foreach ($errors as $key => $error) {
      if (TRUE === $debug) {
        $tmp_error = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
        $debug_output[] = "\n[" . $error->line() . ', \'' . $error->message() . '\', RfcLogLevel::' . $tmp_error[$error->level()] . "],";
        $debug_list[] = "\n[" . $error->line() . ', RfcLogLevel::' . $tmp_error[$error->level()] . "],";
      }

      if (TRUE === $debug) {
        continue;
      }

      self::assertEquals($expected[$key][0] ?? 0, $error->line(), \sprintf('Error line do not match for case: %s', $key));
      self::assertEquals($expected[$key][1] ?? 0, $error->level(), \sprintf('Error level do not match for case: %s', $key));
    }

    if (FALSE === $debug) {
      self::assertEquals(\count($expected), \count($errors), 'Error count do not match');
      return;
    }

    if (TRUE === $debug) {
      print(implode('', $debug_output));
      print("\n");
      print(implode('', $debug_list));
      print("\n");
    }
  }

}
