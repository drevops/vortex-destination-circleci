<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleTestExpr
 *
 * @group sdc_devel
 * @internal
 *
 * cSpell:disable
 */
final class TwigValidatorRuleTestExprTest extends TwigValidatorTestBase {

  /**
   * @covers ::processNode
   *
   * @dataProvider providerTestTwigValidatorTest
   */
  public function testTwigValidatorTest(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorTest.
   *
   * @return array
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorTest(): array {
    return [
      [
        "{% set foo = 'foo' %}
        {% if foo is defined %}{% endif %}
        {% if foo is empty %}{% endif %}
        {% if foo is iterable %}{% endif %}
        {{ foo is null }}
        {{ foo ?? 'bar' }}
        {% if foo is same as(false) %}{% endif %}
        ",
        [
          [2, RfcLogLevel::WARNING],
          [3, RfcLogLevel::WARNING],
          [4, RfcLogLevel::WARNING],
          [5, RfcLogLevel::WARNING],
          [6, RfcLogLevel::WARNING],
          [7, RfcLogLevel::ERROR],
        ],
      ],
    ];
  }

}
