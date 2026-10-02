<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleInclude;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleInclude.
 *
 * CSpell:disable.
 *
 * @internal
 */
#[CoversClass(TwigValidatorRuleInclude::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleIncludeTest extends TwigValidatorTestBase {

  #[DataProvider('providerTestTwigValidatorInclude')]
  public function testTwigValidatorInclude(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorInclude.
   *
   * @return array
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorInclude(): array {
    return [
      [
        "{% include 'links.html.twig' %}
        {% include('links.html.twig') %}
        {% embed 'links.html.twig' %}{% endembed %}
        ",
        [
          [1, RfcLogLevel::WARNING],
          [2, RfcLogLevel::WARNING],
          [3, RfcLogLevel::WARNING],
        ],
      ],
    ];
  }

}
