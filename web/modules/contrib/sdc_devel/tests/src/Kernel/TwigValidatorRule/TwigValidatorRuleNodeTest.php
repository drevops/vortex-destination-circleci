<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleNode
 *
 * @group sdc_devel
 * @internal
 *
 * cSpell:disable
 */
final class TwigValidatorRuleNodeTest extends TwigValidatorTestBase {

  /**
   * @covers ::processNode
   *
   * @dataProvider providerTestTwigValidatorNode
   */
  public function testTwigValidatorNode(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorNode.
   *
   * @return array
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorNode(): array {
    return [
      [
        "{% flush %}
        {% set foo = false %}{% set bar = 'bar' %}{{ foo ? bar }} {# do false positive #}
        {% set foo = ['foo', 'bar'] %}
        {% for item in foo %}{{ item }}{% else %}No foo{% endfor %} {# valid, no message #}
        {% if foo %}{% for item in foo %}{{ item }}{% endfor %}{% else %}No foo{% endif %}
        {% if foo %}<span>{% for item in foo %}{{ item }}{% endfor %}{% else %}No foo</span>{% endif %} {# ignored because string #}
        ",
        [
          [1, RfcLogLevel::ERROR],
          [5, RfcLogLevel::NOTICE],
        ],
      ],
    ];
  }

}
