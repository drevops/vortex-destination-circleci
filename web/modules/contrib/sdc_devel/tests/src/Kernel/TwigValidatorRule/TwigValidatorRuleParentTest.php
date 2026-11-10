<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleParent;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleParent.
 *
 * CSpell:disable.
 *
 * @internal
 */
#[CoversClass(TwigValidatorRuleParent::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleParentTest extends TwigValidatorTestBase {

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
        "{% extends 'links.html.twig' %}
        {% block foo %}
        {{ parent('bar') }}
        {% endblock %}",
        [
          [1, RfcLogLevel::ERROR],
          [3, RfcLogLevel::ERROR],
        ],
      ],
    ];
  }

}
