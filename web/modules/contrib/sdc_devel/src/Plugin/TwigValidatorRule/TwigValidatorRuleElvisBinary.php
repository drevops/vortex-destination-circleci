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
  id: 'elvis_binary',
  twig_node_type: 'Twig\Node\Expression\Binary\ElvisBinary',
  rule_on_name: [],
  label: new TranslatableMarkup('Elvis binary rules'),
  description: new TranslatableMarkup('Rules around Twig Elvis binary (shorthand ternary).'),
)]
final class TwigValidatorRuleElvisBinary extends TwigValidatorRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    if (!$node->hasNode('test')) {
      return [];
    }

    $errors = [];

    $message = new TranslatableMarkup('Use `|default(foo)` filter instead of shorthand ternary `?:`');
    $errors[] = ValidatorMessage::createForNode($id, $node, $message, RfcLogLevel::WARNING);

    return $errors;
  }

}
