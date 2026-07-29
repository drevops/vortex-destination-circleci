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
  id: 'range_binary',
  twig_node_type: 'Twig\Node\Expression\Binary\RangeBinary',
  rule_on_name: [],
  label: new TranslatableMarkup('Range shortcut rule'),
  description: new TranslatableMarkup('Rules around Twig range function shortcut "..".'),
)]
final class TwigValidatorRuleRangeBinary extends TwigValidatorRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    $message = new TranslatableMarkup('Use range() function instead of alias ".."');
    $tip = new TranslatableMarkup('This increase compatibility template engines.');
    return [ValidatorMessage::createForNode($id, $node, $message, RfcLogLevel::WARNING, $tip)];
  }

}
