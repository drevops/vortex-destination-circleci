<?php

declare(strict_types=1);

namespace Drupal\sdc_devel\Plugin\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_devel\Attribute\TwigValidatorRule;
use Drupal\sdc_devel\TwigValidator\TwigNodeFinder;
use Drupal\sdc_devel\TwigValidatorRulePluginBase;
use Drupal\sdc_devel\ValidatorMessage;
use Twig\Node\Node;

/**
 * Plugin implementation of the twig_validator_rule.
 *
 * @see https://www.drupal.org/docs/develop/theming-drupal/twig-in-drupal/functions-in-twig-templates
 * @see web/core/lib/Drupal/Core/Template/TwigExtension.php
 */
#[TwigValidatorRule(
  id: 'function',
  twig_node_type: 'Twig\Node\Expression\FunctionExpression',
  rule_on_name: [
    self::RULE_NAME_IGNORE => [
      // Internal drupal render function that run on all variables.
      'render_var',
    ],
    self::RULE_NAME_WARN => [
      'source' => 'Bad architecture, but sometimes needed for shared static files.',
    ],
    self::RULE_NAME_FORBID => [
      'active_theme' => 'Keep components sandboxed by avoiding functions calling Drupal application.',
      'active_theme_path' => 'Keep components sandboxed by avoiding functions calling Drupal application.',
      'attach_library' => 'The asset library attachment would be more discoverable if declared in the component definition.',
      // @todo see if possible.
      // 'block' => '',
      'constant' => 'Keep components sandboxed by avoiding functions calling Drupal application.',
      'date' => 'Too business & l10n related.',
      'file_url' => 'Should avoid using.',
      'link' => 'PHP URL object, or useless if URL string.',
      'parent' => 'Use slots instead of hard embedding a component in the template with `parent`.',
      'path' => 'Keep components sandboxed by avoiding functions calling Drupal application.',
      'url' => 'Keep components sandboxed by avoiding functions calling Drupal application.',
    ],
    self::RULE_NAME_DEPRECATE => [
      // @todo hard to detect with GetAttrExpression.
      // 'attribute' => '',
      'component' => 'Replace with Twig function include().',
      'pattern' => 'Replace with Twig function include().',
    ],
    self::RULE_NAME_ALLOW => [
      'create_attribute',
      'cycle',
      'max',
      'min',
      'random',
      'range',
      'include',
      'icon',
    ],
  ],
  label: new TranslatableMarkup('Function rules'),
  description: new TranslatableMarkup('Rules around Twig functions.'),
)]
final class TwigValidatorRuleFunction extends TwigValidatorRulePluginBase {

  /**
   * {@inheritdoc}
   */
  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    if (!$node->hasAttribute('name')) {
      return [];
    }

    $name = $node->getAttribute('name');

    $errors = $this->ruleAllowedForbiddenDeprecated($id, $node, $name, 'function');

    if ($func = $this->getRuleMethodToCall($name)) {
      $errors = \array_merge($errors, $this::$func($id, $node));
    }

    return $errors;
  }

  /**
   * Check function random.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function random(string $id, Node $node): array {
    $errors = [];

    $inDefaultFilter = TwigNodeFinder::findParentIs(
      $node,
      'Twig\Node\Expression\Filter\DefaultFilter'
    );

    if (!$inDefaultFilter) {
      $errors[] = ValidatorMessage::createForNode($id, $node, new TranslatableMarkup('Function `random()` must be used in a `default()` filter!'));
    }
    return $errors;
  }

  /**
   * Check function include must have `with_context: false`.
   *
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   *
   * @return \Drupal\sdc_devel\ValidatorMessage[]
   *   An array of errors encountered during the validation process.
   */
  private static function include(string $id, Node $node): array {
    if (!$node->hasNode('arguments')) {
      return [];
    }

    $args = $node->getNode('arguments');
    $children = $args->getIterator();

    $message = new TranslatableMarkup('Function `include()` requires `with_context: false` as the third parameter!');
    $errors = [];
    $isComponent = self::includeIsComponent($children);

    if ($isComponent) {
      $hasContextKey = self::includeHasWithContextKey($children, $id, $node, $message, $errors);
      if (!$hasContextKey) {
        $errors[] = ValidatorMessage::createForNode($id, $node, $message, RfcLogLevel::WARNING);
      }
    }

    return $errors;
  }

  /**
   * Checks if the first child node is a component.
   *
   * @param \Traversable<mixed, mixed> $children
   *   The iterator of child nodes.
   *
   * @return bool
   *   TRUE if the first child node is a component, FALSE otherwise.
   */
  private static function includeIsComponent(\Traversable $children): bool {
    foreach ($children as $key => $value) {
      if ($key === 0) {
        $componentId = $value->getAttribute('value');
        return is_string($componentId) && preg_match('/^[a-z][a-zA-Z0-9_-]*:[a-z][a-zA-Z0-9_-]*$/', $componentId);
      }
    }
    return FALSE;
  }

  /**
   * Checks if the 'with_context' key is present and its value is false.
   *
   * @param \Traversable<mixed, mixed> $children
   *   The iterator of child nodes.
   * @param string $id
   *   The ID of the node.
   * @param \Twig\Node\Node $node
   *   The Twig node being processed.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message to be used for validation errors.
   * @param array $errors
   *   The array to store validation errors.
   *
   * @return bool
   *   TRUE if the 'with_context' key is present and its value is false, FALSE
   *   otherwise.
   */
  private static function includeHasWithContextKey(\Traversable $children, string $id, Node $node, TranslatableMarkup $message, array &$errors): bool {
    foreach ($children as $key => $value) {
      if ($key === 'with_context') {
        if ($value->getAttribute('value') !== FALSE) {
          $errors[] = ValidatorMessage::createForNode($id, $node, $message, RfcLogLevel::WARNING);
        }
        return TRUE;
      }
    }
    return FALSE;
  }

}
