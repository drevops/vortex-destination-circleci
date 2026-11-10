<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleNode;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigValidatorRuleNode.
 *
 * CSpell:disable.
 *
 * @internal
 */
#[CoversClass(TwigValidatorRuleNode::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigValidatorRuleNodeTest extends TwigValidatorTestBase {

  #[DataProvider('providerTestTwigValidatorNode')]
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
