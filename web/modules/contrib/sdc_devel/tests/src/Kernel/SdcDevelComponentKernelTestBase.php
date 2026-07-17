<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\Components\ComponentKernelTestBase;
use Drupal\sdc_devel\Validator;

/**
 * Defines a base class for sdc devel kernel tests.
 *
 * To use simply create a Kernel test with your module or theme.
 * example:
 *
 * @code
 * use Drupal\Tests\sdc_devel\Kernel\SdcDevelComponentKernelTestBase;
 * use PHPUnit\Framework\Attributes\CoversNothing;
 *
 * #[CoversNothing]
 * final class ComponentValidatorTest extends SdcDevelComponentKernelTestBase {
 *
 *   protected static $modules = [
 *     '_my_module_with_component_',
 *   ];
 *
 *   protected static $themes = [
 *     '_my_theme_with_component_',
 *   ];
 * }
 *
 * @endcode
 *
 * You can exclude providers, components by id and error level, see in this
 * class: $levelReport, $excludeProvider, $excludeComponentId.
 */
abstract class SdcDevelComponentKernelTestBase extends ComponentKernelTestBase {

  /**
   * The component plugin manager.
   *
   * @var \Drupal\Core\Theme\ComponentPluginManager
   */
  protected Validator $validator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'sdc_devel',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['sdc_devel_theme_test'];

  /**
   * Level report to include.
   *
   * Only report Warning and above.
   *
   * @var string[]
   */
  protected static $levelReport = [
    RfcLogLevel::WARNING,
    RfcLogLevel::ERROR,
    RfcLogLevel::CRITICAL,
  ];

  /**
   * Component provider to exclude from test.
   *
   * @var string[]
   */
  protected static $excludeProvider = [];

  /**
   * Component id to exclude from test.
   *
   * @var string[]
   */
  protected static $excludeComponentId = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);

    $definition_validator = \Drupal::service('sdc_devel.definition_validator');
    $twig_validator = \Drupal::service('sdc_devel.twig_validator');
    $this->validator = new Validator($twig_validator, $definition_validator);
  }

  /**
   * Validate components with SDC Devel.
   */
  public function testComponents(): void {
    $components = $this->manager->getAllComponents();

    if (!\count($components)) {
      $this->expectNotToPerformAssertions('No components found to test!');
    }

    $results = [];

    foreach ($components as $component) {
      $component_id = $component->getPluginId();
      $this->validator->resetMessages();

      if (\in_array($component_id, static::$excludeComponentId, TRUE)) {
        continue;
      }

      $provider = $component->getPluginDefinition()['provider'] ?? '';

      // Exclude any component from this module by default.
      if (\str_starts_with($provider, 'sdc_devel')) {
        continue;
      }

      if (\in_array($provider, static::$excludeProvider, TRUE)) {
        continue;
      }

      $this->validator->validate($component_id, $component);
      $messages = $this->validator->getMessages();

      if (\count($messages)) {
        $result = $this->formatMessages($component_id, $messages);

        if ($result) {
          $results[] = $result;
        }
      }
    }

    if (\count($results)) {
      $this->fail(\implode("\n", $results));
    }

    $this->assertEmpty($results, 'No component with error found.');
  }

  /**
   * Builds a list of messages for a component.
   *
   * @param string $component_id
   *   Add the component id.
   * @param \Drupal\sdc_devel\ValidatorMessage[] $messages
   *   The component messages.
   *
   * @return string|null
   *   The messages to be printed.
   */
  private function formatMessages(string $component_id, array $messages): ?string {
    $levels = RfcLogLevel::getLevels();

    $output = [];

    foreach ($messages as $message) {
      if (!\in_array($message->level(), static::$levelReport, TRUE)) {
        continue;
      }

      $level = $levels[$message->level()] ?? 'Unknown';
      $line = ($message->line() === 0) ? '-' : $message->line();
      $output[] = \sprintf(' * %s: [%s] Line %d : %s', $level, $message->getType(), $line, $message->messageWithTip());
    }

    if (!empty($output)) {
      return \sprintf("%s\n%s", $component_id, \implode("\n", $output));
    }

    return NULL;
  }

}
