<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Plugin\TwigValidatorRule\TwigValidatorRuleFunction
 *
 * @group sdc_devel
 * @internal
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration,Drupal.Commenting.InlineComment.SpacingBefore
 *
 * cSpell:disable
 */
final class TwigValidatorRuleFunctionTest extends TwigValidatorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sdc_devel_module_test',
  ];

  /**
   * @covers ::processNode
   * @covers ::random
   *
   * @dataProvider providerTestTwigValidatorFunction
   */
  public function testTwigValidatorFunction(string $source, array $expected): void {
    $this->runTestSourceTwigValidator($source, $expected);
  }

  /**
   * Provides tests data for testTwigValidatorFunction.
   *
   * @return iterable
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestTwigValidatorFunction(): iterable {
    yield 'include' => [
      "{{ include('sdc_devel_theme_test:foo', {}, with_context: false) }}
      {{ include('sdc_devel_theme_test:bar', {foo: 'bar'}, with_context: false) }}
      {{ include('not component id') }}",
      [],
    ];

    yield 'include warning' => [
      "
      {{ include('sdc_devel_theme_test:foo') }}
      {{ include('sdc_devel_theme_test:foo', {}) }}
      {{ include('sdc_devel_theme_test:foo', {foo: 'bar'}) }}
      {{ include('sdc_devel_theme_test:foo', {foo: 'bar'}, 'bar') }}
      {{ include('sdc_devel_theme_test:bar', {foo: 'bar'}, with_context: true) }}
      ",
      [
        [2, RfcLogLevel::WARNING],
        [3, RfcLogLevel::WARNING],
        [4, RfcLogLevel::WARNING],
        [5, RfcLogLevel::WARNING],
        [6, RfcLogLevel::WARNING],
      ],
    ];

    yield 'random' => [
      "{% set quux = 'quux' %}
      {% set foo = 'foo-' ~ quux|default(random()) %}
      {% set foo = quux|default(random()) %}
      {% set foo = quux|default(foo ~ '-' ~ random()) %}",
      [],
    ];

    yield 'random error' => [
      "{% set quux = 'foo' %}{% set baz = 'baz' %}
      {% set foo = random() %}
      {{ foo ~ random() }}
      {% set bar = baz ~ '--' ~ random() %}
      {{ random() }}
      {% set qux = 'foo-' ~ quux ~ random() %}
      {# valid #}
      {% set qux = 'foo-' ~ quux|default(random()) %}
      {% set qux = quux|default(random()) %}
      {% set qux = quux|default(foo ~ '-' ~ random()) %}
      {{ bar }}{{ qux }}
        ",
      [
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
        [4, RfcLogLevel::ERROR],
        [5, RfcLogLevel::ERROR],
        [6, RfcLogLevel::ERROR],
      ],
    ];

    yield 'source' => [
      "{{ source('test.html', true) }}",
      [
        [1, RfcLogLevel::WARNING],
      ],
    ];

    yield 'forbid' => [
      "
      {{ active_theme() }}
      {{ active_theme_path() }}
      {{ attach_library('system/maintenance') }}
      {{ constant('PHP_VERSION') }}
      {% set date = date('-2days', 'Europe/Paris') %}{{ date }}
      {{ file_url('public://foo.txt') }}
      {{ link('foo', 'http://foo.bar') }}
      {{ path('<front>') }}
      {{ url('<front>') }}
      ",
      [
        [2, RfcLogLevel::ERROR],
        [3, RfcLogLevel::ERROR],
        [4, RfcLogLevel::ERROR],
        [5, RfcLogLevel::ERROR],
        [6, RfcLogLevel::ERROR],
        [7, RfcLogLevel::ERROR],
        [8, RfcLogLevel::ERROR],
        [9, RfcLogLevel::ERROR],
        [10, RfcLogLevel::ERROR],
      ],
    ];
  }

}
