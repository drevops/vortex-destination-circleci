<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleElvisBinary;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleElvisBinary.
 *
 * CSpell:disable.
 *
 * @internal
 */
#[CoversClass(TwigValidatorRuleElvisBinary::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleElvisBinaryTest extends TwigValidatorTestBase {

  #[DataProvider('providerTestTwigValidatorElvisBinary')]
  public function testTwigValidatorElvisBinary(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorElvisBinary.
   *
   * @return iterable
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorElvisBinary(): iterable {
    yield 'shorthand' => [
      "{% set foo = false %}{% set bar = 'bar' %}
      {{ foo ? foo : bar }}
      {{ foo ?: bar }}",
      [
        [3, RfcLogLevel::WARNING],
      ],
    ];
  }

}
