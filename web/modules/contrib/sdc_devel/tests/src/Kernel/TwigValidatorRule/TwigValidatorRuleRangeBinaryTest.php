<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleRangeBinary;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleRangeBinary.
 *
 * @internal
 */
#[CoversClass(TwigValidatorRuleRangeBinary::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleRangeBinaryTest extends TwigValidatorTestBase {

  #[DataProvider('providerTestTwigValidatorParent')]
  public function testTwigValidatorParent(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorParent.
   *
   * @return array
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorParent(): array {
    return [
      [
        '{% for i in 1..10 %}{{ i }}{% endfor %}',
        [
          [1, RfcLogLevel::WARNING],
        ],
      ],
    ];
  }

}
