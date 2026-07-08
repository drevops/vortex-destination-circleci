<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_devel\TwigValidatorRuleInterface;
use Drupal\sdc_devel\TwigValidatorRulePluginBase;
use Drupal\sdc_devel\ValidatorMessage;
use Drupal\Tests\UnitTestCase;
use Twig\Node\EmptyNode;
use Twig\Node\Node;
use Twig\Node\Nodes;

/**
 * Simple test for Twig Finder helper class.
 *
 * @coversDefaultClass \Drupal\sdc_devel\TwigValidatorRulePluginBase
 *
 * @group sdc_devel
 * @internal
 *
 * phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class TwigValidatorRulePluginTest extends UnitTestCase {

  protected string $pluginId = 'rule_test';

  private array $configuration = [];

  private array $pluginDefinition = [];

  /**
   * @covers ::label
   */
  public function testGetLabel(): void {
    $this->pluginDefinition['label'] = 'Test label';
    $rulePluginTest = $this->getRulePluginTest();
    $this->assertSame('Test label', $rulePluginTest->label());
  }

  /**
   * Test the getRulesByName method.
   *
   * @covers ::getRulesByName
   * @covers ::getNameIgnore
   * @covers ::getNameAllow
   * @covers ::getNameDeprecate
   * @covers ::getNameWarn
   * @covers ::getNameForbid
   */
  public function testGetRulesByName(): void {
    $rule_on_name = [
      -1 => [
        'ignore_me',
      ],
      0 => [
        'allow_me',
      ],
      4 => [
        'warn_me' => 'This is a warning.',
      ],
      3 => [
        'forbid_me' => 'This is forbidden',
      ],
      5 => [
        'deprecate_me' => 'This is deprecated',
      ],
    ];

    $this->pluginDefinition['rule_on_name'] = $rule_on_name;
    $rulePluginTest = $this->getRulePluginTest();

    $this->assertSame($rule_on_name, $rulePluginTest->getRulesByName());
    $this->assertSame($rule_on_name[-1], $rulePluginTest->getNameIgnore());
    $this->assertSame($rule_on_name[0], $rulePluginTest->getNameAllow());
    $this->assertSame($rule_on_name[5], $rulePluginTest->getNameDeprecate());
    $this->assertSame($rule_on_name[4], $rulePluginTest->getNameWarn());
    $this->assertSame($rule_on_name[3], $rulePluginTest->getNameForbid());
  }

  /**
   * Test the processNode method.
   *
   * @covers ::ruleAllowedForbiddenDeprecated
   * @covers ::getRuleMethodToCall
   * @covers ::handleNameCase
   *
   * @dataProvider providerTestProcessNode
   */
  public function testProcessNode(int $ruleLevel, string $name, ?int $expectedLevel, ?string $tip, ?TranslatableMarkup $prefix, ?ValidatorMessage $expectedError): void {
    if ($tip) {
      $this->pluginDefinition['rule_on_name'] = [
        $ruleLevel => [
          $name => $tip,
        ],
      ];
    }
    else {
      $this->pluginDefinition['rule_on_name'] = [
        $ruleLevel => [$name],
      ];
    }

    $rulePluginTest = $this->getRulePluginTest();

    $node = $this->createNode($name);

    $result = $rulePluginTest->processNode('test', $node, [], []);

    if (!$expectedLevel) {
      $this->assertEmpty($result);
      return;
    }

    if (!$expectedError) {
      $expectedError = ValidatorMessage::createForNode('test', $node, new TranslatableMarkup(
        '@prefix Twig @type: `@name`. @tip',
        ['@prefix' => $prefix, '@type' => 'test', '@name' => $name, '@tip' => $tip]
      ), $expectedLevel);
    }

    $this->assertEquals($expectedError, $result[0]);
  }

  /**
   * Provides tests data for testProcessNode.
   *
   * @return array
   *   An array of test data:
   *   - twig template source string
   *   - array of error line and levels expected.
   */
  public static function providerTestProcessNode(): array {
    return [
      [
        -1,
        'ignore_me',
        NULL,
        NULL,
        NULL,
        NULL,
      ],
      [
        0,
        'allow_me',
        0,
        NULL,
        NULL,
        NULL,
      ],
      [
        0,
        'process_node_with_func',
        3,
        NULL,
        NULL,
        ValidatorMessage::createForString('test', new TranslatableMarkup('public')),
      ],
      [
        0,
        'process_node_with_private_func',
        3,
        NULL,
        NULL,
        ValidatorMessage::createForString('test', new TranslatableMarkup('private')),
      ],
      [
        0,
        'process_node_with_protected_func',
        3,
        NULL,
        NULL,
        ValidatorMessage::createForString('test', new TranslatableMarkup('protected')),
      ],
      [
        100,
        'unknown_me',
        4,
        NULL,
        new TranslatableMarkup('Unknown'),
        NULL,
      ],
      [
        4,
        'warn_me',
        4,
        'This is a warning',
        new TranslatableMarkup('Careful with'),
        NULL,
      ],
      [
        3,
        'forbid_me',
        3,
        'This is forbidden',
        new TranslatableMarkup('Forbidden'),
        NULL,
      ],
      [
        5,
        'deprecate_me',
        4,
        'This is deprecated',
        new TranslatableMarkup('Deprecated'),
        NULL,
      ],
    ];
  }

  /**
   * Build an Twig Node.
   *
   * @param string $name
   *   The attribute name for this node.
   *
   * @return \Twig\Node\Node
   *   The Plugin rule.
   */
  private function createNode(string $name): Node {
    $node = new Nodes();
    $node->setNode('node', new EmptyNode());
    $node->setAttribute('name', $name);

    return $node;
  }

  /**
   * Build an instance of plugin.
   *
   * @return \Drupal\sdc_devel\TwigValidatorRuleInterface
   *   The Plugin rule.
   */
  private function getRulePluginTest(): TwigValidatorRuleInterface {
    return new RulePluginTest(
      $this->configuration,
      $this->pluginId,
      $this->pluginDefinition,
    );
  }

}

/**
 * Test Plugin.
 */
final class RulePluginTest extends TwigValidatorRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    $name = $node->getAttribute('name');

    $errors = $this->ruleAllowedForbiddenDeprecated($id, $node, $name, 'test');

    if ($func = self::getRuleMethodToCall($name)) {
      $parent = $node->getNode('node');
      $errors = \array_merge($errors, $this::$func($id, $node, $parent, $definition));
    }

    return $errors;
  }

  /**
   * Internal public test.
   */
  public function processNodeWithFunc(): array {
    return [ValidatorMessage::createForString('test', new TranslatableMarkup('public'))];
  }

  /**
   * Internal protected test.
   */
  protected function processNodeWithProtectedFunc(): array {
    return [ValidatorMessage::createForString('test', new TranslatableMarkup('protected'))];
  }

  /**
   * Internal private test.
   *
   * @phpcs:disable DrupalPractice.Objects.UnusedPrivateMethod.UnusedMethod
   */
  private function processNodeWithPrivateFunc(): array {
    return [ValidatorMessage::createForString('test', new TranslatableMarkup('private'))];
  }

}
