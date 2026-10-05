<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleNullCoalesceBinary;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleNullCoalesceBinary.
 *
 * @internal
 *
 * CSpell:disable.
 */
#[CoversClass(TwigValidatorRuleNullCoalesceBinary::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleTestNullCoalesceBinaryTest extends TwigValidatorTestBase {

  #[DataProvider('providerTestTwigValidatorTest')]
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
        "{% set foo = 'foo' %}{{ foo ?? 'bar' }}",
        [
          [1, RfcLogLevel::WARNING],
        ],
      ],
    ];
  }

}
