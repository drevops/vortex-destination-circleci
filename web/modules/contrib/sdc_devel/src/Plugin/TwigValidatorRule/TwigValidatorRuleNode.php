<?php

declare(strict_types=1);

namespace Drupal\sdc_devel\Plugin\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_devel\Attribute\TwigValidatorRule;
use Drupal\sdc_devel\TwigValidator\NodeAttribute;
use Drupal\sdc_devel\TwigValidatorRulePluginBase;
use Drupal\sdc_devel\ValidatorMessage;
use Twig\Node\Node;

/**
 * Plugin implementation of the twig_validator_rule.
 */
#[TwigValidatorRule(
  id: 'node',
  twig_node_type: 'Twig\Node\Node',
  rule_on_name: [],
  label: new TranslatableMarkup('Node rules'),
  description: new TranslatableMarkup('Rules around Twig node.'),
)]
final class TwigValidatorRuleNode extends TwigValidatorRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    $class = \get_class($node);
    switch ($class) {
      case 'Twig\Node\SandboxNode':
        $message = new TranslatableMarkup('Bad architecture for sandbox: Component calling components.');
        return [ValidatorMessage::createForNode($id, $node, $message)];

      case 'Twig\Node\FlushNode':
        $message = new TranslatableMarkup('Cache management outside of Drupal.');
        return [ValidatorMessage::createForNode($id, $node, $message)];

      case 'Twig\Node\ForNode':
        if (!$node->hasAttribute(NodeAttribute::PARENT)) {
          break;
        }

        $parent = $node->getAttribute(NodeAttribute::PARENT);
        if (!$parent->hasAttribute(NodeAttribute::PARENT)) {
          break;
        }

        $firstParent = $parent->getAttribute(NodeAttribute::PARENT);
        if (!\is_a($firstParent, 'Twig\Node\IfNode')) {
          break;
        }

        if (!$this->checkIfVariableName($node, $firstParent)) {
          break;
        }

        $message = new TranslatableMarkup('Loop in a condition can be replaced by compact syntax without if.');
        return [ValidatorMessage::createForNode($id, $node, $message, RfcLogLevel::NOTICE)];
    }

    return [];
  }

  /**
   * Check variable name between 2 nodes.
   *
   * @param \Twig\Node\Node $forNode
   *   The for node.
   * @param \Twig\Node\Node $ifNode
   *   The if node.
   *
   * @return bool
   *   If the variable name is the same.
   */
  private function checkIfVariableName(Node $forNode, Node $ifNode): bool {
    if (!$forNode->hasNode('seq')) {
      return FALSE;
    }

    $seq = $forNode->getNode('seq');
    if (!$seq->hasAttribute('name')) {
      return FALSE;
    }

    $forVariableName = $seq->getAttribute('name');
    if (!$ifNode->hasNode('tests')) {
      return FALSE;
    }

    $ifNodes = $ifNode->getNode('tests');
    foreach ($ifNodes->getIterator() as $value) {
      $ifVariableName = FALSE;
      if ($value->hasAttribute('name')) {
        $ifVariableName = $value->getAttribute('name');
      }
      if ($value->hasNode('left')) {
        $left = $value->getNode('left');
        if (!$left->hasAttribute('name')) {
          return FALSE;
        }
        $ifVariableName = $left->getAttribute('name');
      }
      if ($ifVariableName === $forVariableName) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
