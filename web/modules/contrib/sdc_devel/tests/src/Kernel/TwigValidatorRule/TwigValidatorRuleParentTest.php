<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleParent
 *
 * @group sdc_devel
 * @internal
 *
 * cSpell:disable
 */
final class TwigValidatorRuleParentTest extends TwigValidatorTestBase {

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
