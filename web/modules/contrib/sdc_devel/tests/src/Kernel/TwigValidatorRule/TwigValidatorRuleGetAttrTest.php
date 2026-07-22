<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleGetAttr
 *
 * @group sdc_devel
 * @internal
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 * cSpell:disable
 */
final class TwigValidatorRuleGetAttrTest extends TwigValidatorTestBase {

  /**
   * @covers ::processNode
   *
   * @dataProvider providerTestTwigValidatorFilter
   */
  public function testTwigValidatorFilter(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorFilter.
   *
   * @return array
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorFilter(): array {
    return [
      // No errors or warning, correct usage.
      [
        "{% set foo = ['foo', 'bar'] %}
        {{ foo['bar'] }}",
        [],
      ],
      [
        "{% set foo = ['foo', '#bar'] %} {{ foo['#bar'] }}",
        [
          [1, RfcLogLevel::WARNING],
        ],
      ],
      [
        "{% set test_prop_object = {} %} {{ test_prop_object.bundle() }}",
        [
          [1, RfcLogLevel::ERROR],
        ],
      ],
    ];
  }

}
