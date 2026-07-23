<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleTernary
 *
 * @group sdc_devel
 * @internal
 *
 * cSpell:disable
 */
final class TwigValidatorRuleTernaryTest extends TwigValidatorTestBase {

  /**
   * @covers ::processNode
   *
   * @dataProvider providerTestTwigValidatorTernary
   */
  public function testTwigValidatorTernary(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorTernary.
   *
   * @return iterable
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorTernary(): iterable {
    yield 'chained ternary' => [
      "{% set foo = false %}{% set bar = false %}{% set baz = false %}{% set qux = 'qux' %}{% set quux = 'quux' %}
      {{ foo ? baz : bar ? qux : baz ? qux : quux }}
      {{ foo ? baz : bar ? qux : baz }}
      {{ foo ? baz : bar }}
      {{ foo ? baz }}",
      [
        [2, RfcLogLevel::ERROR],
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
      ],
    ];

    yield 'boolean' => [
      "{% set qux = 'qux' %}{{ qux == 'bar' ? true : false }}",
      [
        [1, RfcLogLevel::NOTICE],
      ],
    ];
  }

}
