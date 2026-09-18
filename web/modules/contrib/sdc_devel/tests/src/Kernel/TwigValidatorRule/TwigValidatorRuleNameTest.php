<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleName;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleName.
 *
 * CSpell:disable.
 *
 * @internal
 */
#[CoversClass(TwigValidatorRuleName::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleNameTest extends TwigValidatorTestBase {

  #[DataProvider('providerTestTwigValidatorName')]
  public function testTwigValidatorName(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorName.
   *
   * @return array
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorName(): array {
    return [
      [
        "{% set foo = 'foo' %}
        {{ foo }}
        {% set bar = ['foo', 'bar'] %}
        {% for key, item in bar %}
          {{ key }}{{ item }}
        {% endfor %}
        {% for item_2 in bar %}
          {{ item_2 }}
        {% endfor %}
        {% set my_attributes = create_attribute() %}
        {{ my_attributes }}
        {# Test injected #}
        {{ attributes }}
        {{ variant }}
        {{ _self }}
        {{ _key }}
        {% set users = [{name: 'foo'},{name: 'bar'}] %}
        {% for user in users %}
          {{ loop.index }} - {{ user.name }}
        {% endfor %}
        ",
        [],
      ],
      [
        "{{ not_set_1 }}
        {% for key, item in not_set_2 %}
          {{ key }}{{ item }}{{ not_set_3 }}
        {% endfor %}
        {% for item in not_set_4 %}
          {{ item_not_set }}
        {% endfor %}
        {% for item in not_set_5 %}
          {{ item }}
        {% endfor %}
        {{ componentMetadata.path }}
        {% for item in ['foo'] %}{{ loop.parent.variant }}{% endfor %}
        ",
        [
          [1, RfcLogLevel::ERROR],
          [2, RfcLogLevel::ERROR],
          [3, RfcLogLevel::ERROR],
          [5, RfcLogLevel::ERROR],
          [6, RfcLogLevel::ERROR],
          [8, RfcLogLevel::ERROR],
          [11, RfcLogLevel::ERROR],
          [12, RfcLogLevel::ERROR],
        ],
      ],
    ];
  }

}
