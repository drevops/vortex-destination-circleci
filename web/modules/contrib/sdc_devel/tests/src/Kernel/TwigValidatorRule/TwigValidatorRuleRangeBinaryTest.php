<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleRangeBinary
 *
 * @group sdc_devel
 * @internal
 */
final class TwigValidatorRuleRangeBinaryTest extends TwigValidatorTestBase {

  /**
   * @covers ::processNode
   *
   * @dataProvider providerTestTwigValidatorParent
   */
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
        "{% for i in 1..10 %}{{ i }}{% endfor %}",
        [
          [1, RfcLogLevel::WARNING],
        ],
      ],
    ];
  }

}
