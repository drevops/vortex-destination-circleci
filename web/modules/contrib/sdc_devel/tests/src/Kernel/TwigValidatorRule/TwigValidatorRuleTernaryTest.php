<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleTernary;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleTernary.
 *
 * CSpell:disable.
 *
 * @internal
 */
#[CoversClass(TwigValidatorRuleTernary::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleTernaryTest extends TwigValidatorTestBase {

  #[DataProvider('providerTestTwigValidatorTernary')]
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
