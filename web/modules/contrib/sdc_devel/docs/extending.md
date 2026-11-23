# Extending

Rules are plugins. Add one from any module by dropping a class in
`src/Plugin/TwigValidatorRule/`.

## A minimal rule

```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Plugin\TwigValidatorRule;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_devel\Attribute\TwigValidatorRule;
use Drupal\sdc_devel\TwigValidatorRulePluginBase;
use Drupal\sdc_devel\ValidatorMessage;
use Twig\Node\Node;

#[TwigValidatorRule(
  id: 'range_binary',
  twig_node_type: 'Twig\Node\Expression\Binary\RangeBinary',
  rule_on_name: [],
  label: new TranslatableMarkup('Range shortcut rule'),
  description: new TranslatableMarkup('Rules around Twig range function shortcut "..".'),
)]
final class MyRule extends TwigValidatorRulePluginBase {

  public function processNode(string $id, Node $node, array $definition, array $variableSet): array {
    $message = new TranslatableMarkup('Use range() function instead of alias ".."');
    $tip = new TranslatableMarkup('This increase compatibility template engines.');

    return [ValidatorMessage::createForNode($id, $node, $message, RfcLogLevel::WARNING, $tip)];
  }

}
```

`twig_node_type` decides which nodes reach `processNode()`. Return an array of
`ValidatorMessage`, or an empty array when there is nothing to say.

`processNode()` receives:

| Argument | Content |
| --- | --- |
| `$id` | The component plugin id, empty when validating a raw source string. |
| `$node` | The Twig node being visited. |
| `$definition` | The component props and slots, flattened as `name => type`. |
| `$variableSet` | The variables set in the template. |

## Name based rules

For filters and functions, most of the work is declarative. List names in `rule_on_name`
and the base class produces the message, severity and tip:

```php
rule_on_name: [
  self::RULE_NAME_IGNORE => ['render_var'],
  self::RULE_NAME_ALLOW => ['min', 'max'],
  self::RULE_NAME_WARN => [
    'source' => 'Bad architecture, but sometimes needed for shared static files.',
  ],
  self::RULE_NAME_DEPRECATE => [
    'component' => 'Replace with Twig function include().',
  ],
  self::RULE_NAME_FORBID => [
    'url' => 'Keep components sandboxed by avoiding functions calling Drupal application.',
  ],
],
```

Then call the helper from `processNode()`:

```php
$errors = $this->ruleAllowedForbiddenDeprecated($id, $node, $name, 'function');
```

Remember that a name in none of these buckets is reported as `Unknown` with a warning.
Adding a name to the allow list is how you silence it.

## Per name checks

On top of the buckets, a rule can inspect the arguments of one specific filter or
function. Add a private static method named after it and dispatch with
`getRuleMethodToCall()`:

```php
if ($func = $this->getRuleMethodToCall($name)) {
  $errors = \array_merge($errors, $this::$func($id, $node, $node->getNode('node'), $definition));
}
```

The name is stripped of its separators and matched case insensitively, so `set_attribute`
resolves to `setAttribute()` and `default` to `default()`.

## Walking the tree

Before rules run, every node is given its parent as an attribute, so a rule can look at
its surroundings:

```php
use Drupal\sdc_devel\TwigValidator\NodeAttribute;
use Drupal\sdc_devel\TwigValidator\TwigNodeFinder;

$parent = $node->getAttribute(NodeAttribute::PARENT);
$inside_a_loop = TwigNodeFinder::findParentIs($node, 'Twig\Node\ForNode');
```

Watch out for the wrappers Drupal and Twig add around expressions. A printed expression is
wrapped in a `Twig\Node\CheckToStringNode`, and an `{% if foo %}` condition is wrapped in a
`Twig\Node\Expression\Test\TrueTest`. Unwrap them before reading a name or a value, or the
rule will silently stop matching on the next Twig release.

## Testing a rule

Kernel tests extend `TwigValidatorTestBase` and assert on a raw template source. Each
expected entry is a line number and a severity:

```php
$this->runTestSourceTwigValidator(
  "{{ 'foo'|my_filter }}",
  [
    [1, RfcLogLevel::WARNING],
  ],
);
```

Pass `TRUE` as the third argument to print the messages the validator actually produced
instead of asserting, which is the quickest way to write the expectations of a new rule.
