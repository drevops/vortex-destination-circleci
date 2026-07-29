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
 *
 * @see https://www.drupal.org/docs/develop/theming-drupal/twig-in-drupal/filters-modifying-variables-in-twig-templates
 * @see web/core/lib/Drupal/Core/Template/TwigExtension.php
 */
#[TwigValidatorRule(
  id: 'filter',
  twig_node_type: 'Twig\Node\Expression\FilterExpression',
  rule_on_name: [
    self::RULE_NAME_IGNORE => [
      // Internal drupal escape filter that run on all variables.
      'drupal_escape',
      'escape',
      'e',
    ],
    self::RULE_NAME_WARN => [
      'placeholder' => 'Calls to application state.',
      // Not possible: alias drupal_escape, run as internal on all variables.
      // 'e' => 'Useless, Drupal already escape all variables.',
      // 'escape' => 'Useless, Drupal already escape all variables.',
      // Can not work because replaced by render_var.
      // 'raw' => 'May be dangerous, data must be escaped.',.
    ],
    self::RULE_NAME_DEPRECATE => [
      'safe_join' => '',
      'spaceless' => 'The spaceless filter is deprecated as of Twig 3.12. While not a full replacement, you can check the whitespace control features.',
    ],
    self::RULE_NAME_FORBID => [
      'add_suggestion' => 'Bad architecture. Drupal legacy.',
      'clean_unique_id' => 'Calls to application state. Use random function inside default filter.',
      'convert_encoding' => 'Needs specific PHP extension.',
      'date_modify' => 'PHP object manipulation must be avoided.',
      'date' => 'PHP object manipulation must be avoided.',
      'format_date' => 'Business related. Load config entities.',
      'render' => 'Please ensure you are not rendering content too early.',
      'without' => 'Avoid `without` filter on slots, which must stay opaque. Allowed with attributes objects until #3296456 is fixed.',
    ],
    self::RULE_NAME_ALLOW => [
      // Jinja filters.
      'abs',
      'batch',
      'capitalize',
      'default',
      'first',
      'format',
      'join',
      'json_encode',
      'last',
      'length',
      'lower',
      'map',
      'replace',
      'reverse',
      'round',
      'slice',
      'sort',
      'striptags',
      'title',
      'trim',
      'upper',
      'url_encode',
      // Twig filters with Drupal.
      'add_class',
      'clean_class',
      'clean_id',
      'set_attribute',
      't',
      'trans',
      // Twig filters.
      'column',
      'filter',
      'keys',
      'merge',
      'nl2br',
      'number_format',
      'reduce',
      'split',
    ],
  ],
  label: new TranslatableMarkup('Filter rules'),
  description: new TranslatableMarkup('Rules around Twig filters.'),
)]
final class TwigValidatorRuleFilter extends TwigValidatorRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    if (!$attribute = $node->getAttribute('twig_callable')) {
      return [];
    }

    $name = $attribute->getName();

    if (!\is_string($name) || in_array($name, $this->getNameIgnore())) {
      return [];
    }

    $errors = $this->ruleAllowedForbiddenDeprecated($id, $node, $name, 'filter');

    if ($func = $this->getRuleMethodToCall($name)) {
      $parent = $node->getNode('node');
      $errors = \array_merge($errors, $this::$func($id, $node, $parent, $definition));
    }

    return $errors;
  }

  /**
   * Check filter abs.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Twig\Node\Node $parent
   *   The parent node of the Twig node.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function abs(string $id, Node $node, Node $parent): array {
    $errors = [];

    if (\is_a($parent, 'Twig\Node\Expression\ConstantExpression')) {
      if ($parent->hasAttribute('value')) {
        $value = $parent->getAttribute('value');

        if (\is_string($value)) {
          $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `abs` can only be applied on number, @type found!', ['@type' => 'string']));
        }
        elseif (\is_bool($value)) {
          $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `abs` can only be applied on number, @type found!', ['@type' => 'boolean']));
        }
        elseif (NULL === $value) {
          $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `abs` can only be applied on number, @type found!', ['@type' => 'null']));
        }
      }
    }

    return $errors;
  }

  /**
   * Check filter add_class.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Twig\Node\Node $parent
   *   The parent node of the Twig node.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function addClass(string $id, Node $node, Node $parent): array {
    $errors = [];

    if (\is_a($parent, 'Twig\Node\Expression\ConstantExpression')) {
      if ($parent->hasAttribute('value')) {
        $value = $parent->getAttribute('value');

        if (\is_string($value)) {
          $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `add_class` can not be used on `string`, only `mapping`!'));
        }
      }
    }

    return $errors;
  }

  /**
   * Check filter clean_id.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Twig\Node\Node $parent
   *   The parent node of the Twig node.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function cleanId(string $id, Node $node, Node $parent): array {
    $errors = [];

    if (\is_a($parent, 'Twig\Node\Expression\ConstantExpression')) {
      if ($parent->hasAttribute('value')) {
        $value = $parent->getAttribute('value');

        if (!\is_string($value)) {
          $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `clean_id` can only be applied on string!'));
        }
      }
    }

    return $errors;
  }

  /**
   * Check filter default.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Twig\Node\Node $parent
   *   The parent node of the Twig node.
   * @param array $definition
   *   The component flatten slot and props as name => type.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function default(string $id, Node $node, Node $parent, array $definition): array {
    $errors = [];

    if (\is_a($parent, 'Twig\Node\Expression\ConstantExpression') && $parent->hasAttribute('value')) {
      $value = $parent->getAttribute('value');
      if (\is_bool($value) || NULL === $value) {
        $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `default` is not for booleans or null!'));
      }
    }

    // Detect {{ foo|default(bar) }} where bar is boolean case.
    // Seems hard to detect if bar is a boolean set anywhere before in the
    // template. So we detect only injected variables.
    if (\is_a($parent, 'Twig\Node\Expression\Ternary\ConditionalTernary')) {
      foreach ($parent->getIterator() as $expr) {
        if (!$expr instanceof Node) {
          continue;
        }

        if (!self::validateFilterExpression($expr)) {
          continue;
        }

        $variable_name = $expr->getNode('node')->getNode('expr')->getAttribute('name');

        if (!isset($definition[$variable_name]) || 'boolean' !== $definition[$variable_name]) {
          continue;
        }

        $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup("Don't use `default` filter on boolean."));
      }
    }

    if ('Twig\Node\Expression\Filter\DefaultFilter' !== get_class($node)) {
      return $errors;
    }

    $inside_default = NULL;
    // $inside_default_is_variable = FALSE;
    if ($parent->hasNode('right')) {
      $right = $parent->getNode('right');
      if ($right->hasAttribute('name')) {
        // $inside_default_is_variable = TRUE;
        $inside_default = $right->getAttribute('name');
      }
      elseif ($right->hasAttribute('value')) {
        $inside_default = $right->getAttribute('value');
      }
    }

    // Get variable on which default filter is applied.
    $left_default = NULL;
    if ($parent->hasNode('test')) {
      $node_parent = $parent->getNode('test');
      if ($node_parent->hasNode('node') && $node_parent->getNode('node')->hasAttribute('name')) {
        $left_default = $node_parent->getNode('node')->getAttribute('name');
      }
    }

    // Detect {{ foo|default(foo) }} case, when default is same as variable.
    if ($left_default && $left_default === $inside_default) {
      $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `default` return the value itself!'), RfcLogLevel::WARNING);
    }

    // Detect {{ foo|default(false) }} or {{ foo|default(true) }} case.
    if (is_bool($inside_default)) {
      $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup("Don't use `default` filter with boolean."), RfcLogLevel::WARNING);
    }

    return $errors;
  }

  /**
   * Get filter expression name.
   *
   * @param \Twig\Node\Node $expr
   *   The Twig node being processed.
   *
   * @return bool
   *   If we can have a name.
   */
  private static function validateFilterExpression(Node $expr): bool {
    return \is_a($expr, 'Twig\Node\Expression\FilterExpression') &&
        $expr->hasNode('node') &&
        $expr->getNode('node')->hasNode('expr') &&
        $expr->getNode('node')->getNode('expr')->hasAttribute('name');
  }

  /**
   * Check filter set_attribute.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Twig\Node\Node $parent
   *   The parent node of the Twig node.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function setAttribute(string $id, Node $node, Node $parent): array {
    $errors = [];

    if (\is_a($parent, 'Twig\Node\Expression\FilterExpression')) {
      $filter_name = $parent->getAttribute('twig_callable')->getName();
      $allowed_previous_filters = ['map', 'reverse', 'split', 'first', 'last', 'default', 'set_attribute'];

      if (!\in_array($filter_name, $allowed_previous_filters, TRUE)) {
        $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup(
          'Filter `set_attribute` do not allow previous filter: `@filter`!',
          [
            '@filter' => $filter_name,
          ]
        ));
      }
    }

    if (!$node->hasNode('arguments')) {
      return [];
    }

    $target = $node->getNode('arguments');
    foreach ($target->getIterator() as $index => $arg) {
      if (1 !== $index) {
        continue;
      }
      if (!\is_object($arg)) {
        continue;
      }

      if (\is_a($arg, 'Twig\Node\Expression\ArrayExpression')) {
        foreach ($arg->getIterator() as $key => $value) {
          if (!$value instanceof Node) {
            continue;
          }

          if (0 !== $key || 0 === $value->getAttribute('value')) {
            continue;
          }

          $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `set_attribute` second argument can not be a mapping!'));
        }
      }

      if (!\is_a($arg, 'Twig\Node\Expression\ConstantExpression')) {
        continue;
      }

      if (!$arg->hasAttribute('value')) {
        continue;
      }

      $value = $arg->getAttribute('value');

      if (NULL !== $value) {
        continue;
      }

      $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `set_attribute` second argument can not be null!'));
    }

    return $errors;
  }

  /**
   * Check filter t.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Twig\Node\Node $parent
   *   The parent node of the Twig node.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function t(string $id, Node $node, Node $parent): array {
    $errors = [];

    if (\is_a($parent, 'Twig\Node\CheckToStringNode')) {
      if ($parent->hasNode('expr')) {
        $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `trans` or `t` unsafe translation, do not translate variables!'), RfcLogLevel::NOTICE);
      }
    }

    if (\is_a($parent, 'Twig\Node\Expression\ConstantExpression')) {
      if ($parent->hasAttribute('value')) {
        $value = $parent->getAttribute('value');

        if (empty($value)) {
          $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `trans` or `t` is applied on an empty string'), RfcLogLevel::NOTICE);
        }
      }
    }

    if (\is_a($parent, 'Twig\Node\Expression\ArrayExpression')) {
      $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Filter `trans` or `t` can only be applied on string!'));
    }

    return $errors;
  }

  /**
   * Check filter trans. Same as t.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Twig\Node\Node $parent
   *   The parent node of the Twig node.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function trans(string $id, Node $node, Node $parent): array {
    // @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
    return self::t($id, $node, $parent);
  }

}
