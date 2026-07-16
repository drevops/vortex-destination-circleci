<?php

declare(strict_types=1);

namespace Drupal\sdc_devel\Plugin\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_devel\Attribute\TwigValidatorRule;
use Drupal\sdc_devel\TwigValidatorRulePluginBase;
use Drupal\sdc_devel\ValidatorMessage;
use Twig\Node\Node;

/**
 * Plugin implementation of the twig_validator_rule.
 */
#[TwigValidatorRule(
  id: 'ternary',
  twig_node_type: 'Twig\Node\Expression\Ternary\ConditionalTernary',
  rule_on_name: [],
  label: new TranslatableMarkup('Conditional rules'),
  description: new TranslatableMarkup('Rules around Twig Conditional.'),
)]
final class TwigValidatorRuleTernary extends TwigValidatorRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    if (!$node->hasNode('test')) {
      return [];
    }

    $errors = [];

    $this->checkChainedTernary($id, $node, $errors);
    $this->checkBooleanResultTernary($id, $node, $errors);

    return $errors;
  }

  /**
   * Checks for chained ternary expressions.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param array $errors
   *   The array to store errors.
   */
  private function checkChainedTernary(string $id, Node $node, array &$errors): void {
    $right = $node->getNode('right');
    if (\is_a($right, 'Twig\Node\Expression\Ternary\ConditionalTernary')) {
      $message = new TranslatableMarkup('No chained ternary');
      $errors[] = ValidatorMessage::createForNode($id, $node, $message);
    }
  }

  /**
   * Checks for ternary expressions with boolean results.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param array $errors
   *   The array to store errors.
   */
  private function checkBooleanResultTernary(string $id, Node $node, array &$errors): void {
    $left = $node->getNode('left');
    $right = $node->getNode('right');

    if (!\is_a($left, 'Twig\Node\Expression\ConstantExpression') || !\is_a($right, 'Twig\Node\Expression\ConstantExpression')) {
      return;
    }

    if (!$left->hasAttribute('value') || !$right->hasAttribute('value')) {
      return;
    }

    if ($left->getAttribute('value') === TRUE && $right->getAttribute('value') === FALSE) {
      $message = new TranslatableMarkup('Ternary test with boolean result');
      $errors[] = ValidatorMessage::createForNode($id, $node, $message, RfcLogLevel::NOTICE);
    }
  }

}
