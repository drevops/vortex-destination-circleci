# Rules

A component is validated in two passes: the YAML definition, then the Twig template.

## Definition checks

The `*.component.yml` file goes through the core component validator first, so schema
errors are reported as is. On top of that, SDC Devel reports:

| Message | Meaning |
| --- | --- |
| A single variant do not need to be declared. | Only one variant is defined, drop the `variants` key. |
| Required slots are not recommended. | A slot should stay optional. |
| Slots should not have type, perhaps this should be a prop. | A typed slot is usually a prop. |
| Missing type for this property. | The prop has no `type`. |
| Default value must be in the enum. | The `default` is not one of the `enum` values. |
| All key-value pairs in meta:enum string are identical. | The `meta:enum` labels add nothing. |
| Empty object. / Empty array. / Array of empty object. | The prop declares a shape with no properties. |

Stories files are validated too, and their errors are reported under the component.

## Twig checks

The template is tokenized and parsed into a Twig node tree. It is never rendered. Three
visitors then run on that tree:

1. The parent of each node is stored, so a rule can walk back up the tree.
2. Variables are collected. Anything set or declared as a prop or slot but never printed
   is reported as an unused variable.
3. Every rule plugin is applied to the nodes matching its Twig node type.

## Severities

Each rule plugin declares names in five buckets. What a name is not listed in matters as
much as what it is listed in:

| Bucket | Severity | Message prefix |
| --- | --- | --- |
| Ignore | none | Internal, never reported. |
| Allow | none | Explicitly fine to use. |
| Warn | Warning | `Careful with` |
| Deprecate | Warning | `Deprecated` |
| Forbid | Error | `Forbidden` |
| *anything else* | Warning | `Unknown` |

The last line is the important one. The allow list: a filter or function that no plugin
knows about raises an `Unknown` warning rather than passing silently. That is what
catches custom Twig extensions leaking into a component.

Forbidden and deprecated names carry a tip explaining the reasoning, which is what the
report page and the Drush table show in the message column.

## Rule plugins

Plugins are matched against a Twig node type. One plugin sees every node of that type in
the template.

| Plugin id | Twig node type | Checks |
| --- | --- | --- |
| `filter` | `FilterExpression` | Filter names, plus per filter argument checks. |
| `function` | `FunctionExpression` | Function names, plus per function argument checks. |
| `name` | `NameExpression` | Variable names, undefined and internal variables. |
| `node` | `Node` | Structural rules, such as a loop inside a condition. |
| `constant` | `ConstantExpression` | Constant values used where they should not be. |
| `get_attribute` | `GetAttrExpression` | Attribute access on props and objects. |
| `include` | `IncludeNode` | `include` and `embed` usage. |
| `parent` | `ParentExpression` | `parent()` calls. |
| `ternary` | `ConditionalTernary` | Conditional expressions. |
| `elvis_binary` | `Binary\ElvisBinary` | Shorthand ternary `?:`. |
| `null_coalesce` | `Binary\NullCoalesceBinary` | Null coalescing `??`. |
| `range_binary` | `Binary\RangeBinary` | Range shortcut `..`. |
| `test` | `TestExpression` | Twig tests, such as `is defined`. |

## Examples

A few of the names that are refused, with the reason attached to them:

```twig
{# Forbidden: keep components sandboxed. #}
{{ path('<front>') }}
{{ url('entity.node.canonical', {node: 1}) }}
{{ active_theme() }}
{{ constant('FOO') }}

{# Forbidden: the library belongs in the component definition. #}
{{ attach_library('my_theme/my_lib') }}

{# Forbidden: rendering too early, and business logic. #}
{{ content|render }}
{{ node.created|format_date('html_date') }}

{# Deprecated: use include() instead. #}
{{ component('my_theme:card', {}) }}

{# Careful with: shared static files only. #}
{{ source('@my_theme/icons/foo.svg') }}
```

And a few structural ones:

```twig
{# Filter `default` is not for booleans or null. #}
{{ true|default('error') }}

{# Filter `default` returns the value itself. #}
{{ foo|default(foo) }}

{# Loop in a condition can be replaced by compact syntax without if. #}
{% if items %}{% for item in items %}{{ item }}{% endfor %}{% endif %}
```

The full lists live in the plugin attributes under
`src/Plugin/TwigValidatorRule/`, each name carrying its own tip.
