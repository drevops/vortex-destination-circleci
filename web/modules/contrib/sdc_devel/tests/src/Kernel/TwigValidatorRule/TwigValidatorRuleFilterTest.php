<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\sdc_devel\Kernel\TwigValidatorTestBase;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleFilter
 *
 * @group sdc_devel
 * @internal
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration,Drupal.Commenting.InlineComment.SpacingBefore,Drupal.Files.LineLength.TooLong
 * cSpell:disable
 */
final class TwigValidatorRuleFilterTest extends TwigValidatorTestBase {

  /**
   * @covers ::processNode
   * @covers ::abs
   * @covers ::addClass
   * @covers ::cleanId
   * @covers ::default
   * @covers ::validateFilterExpression
   * @covers ::setAttribute
   * @covers ::t
   *
   * @dataProvider providerTestTwigValidatorFilter
   */
  public function testTwigValidatorFilter(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorFilter.
   *
   * @return iterable
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorFilter(): iterable {
    yield 'allow' => [
      "
        {% set abs_number = -5 %}
        {{ abs_number|abs }}
        {% set batch_items = ['a', 'b', 'c', 'd'] %}
        <table>
          {% for row in batch_items|batch(3, 'No item') %}
            <tr>
              {% for index, column in row %}
                <td>{{ index }} - {{ column }}</td>
              {% endfor %}
            </tr>
          {% endfor %}
        </table>
        {{ 'my first car'|capitalize }}
        {# {{ var|default('var is not defined') }} #}
        {{ [1, 2, 3, 4]|first }}
        {% set format_item = 'foo' %}
        {{ '%s and %s.'|format(format_item, 'bar') }}
        {{ [1, 2, 3]|join }}
        {% set data_json = {foo: 'bar'} %}
        {{ data_json|json_encode() }}
        {{ [1, 2, 3, 4]|last }}
        {% set test_length = 10 %}
        {{ test_length|length }}
        {{ 'FOO'|lower }}
        {% set map_items = {
          'Foo': 'Baz',
          'Bar': 'Qux',
        } %}
        {{ map_items|map((value, key) => \"#{key} #{value}\")|join(', ') }}
        {% set replace_item = 'foo' %}
        {{ 'I like %this% and %that%.'|replace({'%this%': replace_item, '%that%': 'bar'}) }}
        {% for reverse_key, reverse_value in {1: 'a', 2: 'b', 3: 'c'}|reverse %}
          {{ reverse_key }}: {{ reverse_value }}
        {%- endfor %}
        {{ 42.55|round }}
        {{ '12345'|slice(1, 2) }}
        {% set sort_items = [
          {name: 'Apples', quantity: 5},
          {name: 'Oranges', quantity: 2},
          {name: 'Grapes', quantity: 4},
        ] %}
        {% for sort_item in sort_items|sort((a, b) => a.quantity <=> b.quantity)|column('name') %}
          {{ sort_item }}
        {% endfor %}
        {% set striptags_html = '<p>foo<span>bar</span></p>' %}
        {{ striptags_html|striptags }}
        {{ 'my first car'|title }}
        {{ '  I like Drupal.  '|trim }}
        {{ 'welcome'|upper }}
        {{ 'path-seg*ment'|url_encode }}
        - Twig filters with Drupal. -
        {{ {}|add_class('my-new-body-class') }}
        {{ '1 2 Foô'|clean_class }}
        {{ '1 2 Foô'|clean_id }}
        {{ {}|set_attribute('fetchpriority', 'auto') }}
        {{ 'foo'|t }}
        {{ 'foo'|trans }}
        - Twig filters. -
        {% set column_items = [{ 'fruit' : 'apple'}, {'fruit' : 'orange' }] %}
        {% set column_test = column_items|column('fruit') %} {{ column_test|join(', ') }}
        {% for keys_key in ['a', 'b', 'c', 'd']|keys %}
          {{ keys_key }}
        {% endfor %}
        {% set merge_values = [1, 2] %}
        {% set merge_values = merge_values|merge(['apple', 'orange']) %}
        {{ 'I like Twig.\nYou will like it too.'|nl2br }}
        {{ 9800.333|number_format(2, '.', ',') }}
        {% set split_items = 'one,two,three'|split(',') %} {{ split_items|join(', ') }}
        {% set filter_sizes = [34, 36, 38, 40, 42] %}
        {% for v in filter_sizes|filter(v => v > 38) -%}
          {{ v }}
        {% endfor %}
        {{ [1, 2, 3]|reduce((c, v) => c + v) }}
      ",
      [],
    ];

    // Filter drupal_escape use deprecated twig_escape_filter_is_safe.
    // Since twig/twig 3.9: Using the internal "twig_escape_filter_is_safe"
    // function is deprecated.
    yield 'ignore' => [
      "
        {# {{ 'foo'|drupal_escape }} #}
        {{ 'foo'|escape }}
        {{ 'foo'|e }}
      ",
      [],
    ];

    yield 'warn' => [
      "{% set var_placeholder = 'foo' %} {{ var_placeholder|placeholder }}",
      [
        [1, RfcLogLevel::WARNING],
      ],
    ];

    yield 'deprecate' => [
      "
        {% set safe_test = ['foo', 'bar'] %}{{ safe_test|safe_join(', ') }}
        {{ ' foo '|spaceless }}
      ",
        [
          [2, RfcLogLevel::WARNING],
          [3, RfcLogLevel::WARNING],
        ],
    ];

    yield 'forbid' => [
      "
        {{ {'#theme': 'foo'}|add_suggestion('bar') }}
        {{ 'foo'|clean_unique_id }}
        {{ 'foo'|convert_encoding('UTF-8', 'iso-2022-jp') }}
        {{ ''|date_modify('+1 day')|date('Y-M-d') }}
        {{ 1669324282|format_date('html_date') }}
        {{ {}|render }}
        {{ {}|without('links') }}
      ",
      [
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
        [4, RfcLogLevel::ERROR],
        [5, RfcLogLevel::ERROR],
        [5, RfcLogLevel::ERROR],
        [6, RfcLogLevel::ERROR],
        [7, RfcLogLevel::ERROR],
        [8, RfcLogLevel::ERROR],
      ],
    ];

    // Test function function TwigValidatorRuleFilter::abs().
    yield 'abs' => [
      "
        {{ true | abs }}
        {{ 'foo' | abs }}
        {{ null | abs }}
      ",
      [
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
        [4, RfcLogLevel::ERROR],
      ],
    ];

    // Test function function TwigValidatorRuleFilter::add_class().
    yield 'add_class' => [
      "{{ 'foo' | add_class}}",
      [
        [1, RfcLogLevel::ERROR],
      ],
    ];

    // Test function function TwigValidatorRuleFilter::clean_id().
    yield 'clean_id' => [
      "
        {{ 3 | clean_id }}
        {{ -5 | clean_id }}
        {{ 2.33 | clean_id }}
        {{ true | clean_id }}
      ",
      [
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
        [4, RfcLogLevel::ERROR],
        [5, RfcLogLevel::ERROR],
      ],
    ];

    // Test function function TwigValidatorRuleFilter::default().
    yield 'default' => [
      "
        {{ true | default('error') }}
        {{ false | default('error') }}
        {{ null | default('error') }}
        {% set foo = 'foo' %}{{ foo | default(false) }}
        {{ foo | default(true) }}
        {{ foo | default(foo) }}
        {% set foo = 'foo' %}{% set bar = true %}
        {{ foo | default(bar) }}
      ",
      [
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
        [4, RfcLogLevel::ERROR],
        [5, RfcLogLevel::WARNING],
        [6, RfcLogLevel::WARNING],
        [7, RfcLogLevel::WARNING],
      ],
    ];

    // Test function function TwigValidatorRuleFilter::set_attribute().
    yield 'set_attribute' => [
      "
        {{ 'foo' | join(',') | set_attribute('bar', 'baz') }}
        {{ 'foo' | set_attribute('bar', {'baz': 'qux'}) }}
        {{ 'foo' | set_attribute('bar', null) }}
      ",
      [
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
        [4, RfcLogLevel::ERROR],
      ],
    ];

    // Test function function TwigValidatorRuleFilter::t() and trans().
    yield 'trans' => [
      "
        {{ '' | t }}
        {{ '' | trans }}
        {{ ['foo'] | t }}
        {{ ['foo'] | trans }}
      ",
      [
        [2, RfcLogLevel::NOTICE],
        [3, RfcLogLevel::NOTICE],
        [4, RfcLogLevel::ERROR],
        [5, RfcLogLevel::ERROR],
      ],
    ];
  }

}
