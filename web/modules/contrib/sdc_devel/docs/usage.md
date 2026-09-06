# Usage

Three ways to run the validation: the report page, the Drush command, and a kernel test
base class you can extend from your own module or theme.

## Report page

Once the module is installed, the report lives under **Reports > UI components**:

| Page | Path |
| --- | --- |
| Overview | `/admin/reports/ui-components` |
| Details | `/admin/reports/ui-components/details` |
| Single component | `/admin/reports/ui-components/component/{component_id}` |

The **Overview** tab lists every component found on the site with its number of messages.
The **Details** tab expands the messages for all of them at once. Both are read only.

Access needs the two permissions `access components page` and `access site reports`.

## Drush

```bash
drush sdc-devel:validate <project> [component_id] [--install]
```

The alias is `sdcv`. `project` is the machine name of the module or theme providing the
components, not the component id. Pass a comma separated list to validate several
projects in one run.

```bash
# Validate every component of a theme.
drush sdcv my_theme

# Validate a single component.
drush sdcv my_theme my_theme:card

# Validate two projects.
drush sdcv my_theme,my_module

# Install the project first, validate, then uninstall it again.
drush sdcv my_theme --install
```

Results come back as a table with the `component`, `severity`, `message`, `type`, `line`
and `source` columns, so the usual Drush formatting options apply, for example
`--format=json` or `--fields=component,message`.

Use `--install` when the project you want to check is not enabled yet. The command
installs it, validates, then restores the previous state.

## Kernel test

Extend `SdcDevelComponentKernelTestBase` to fail your test suite when a component
regresses. The test validates every component of the modules and themes you install,
except the ones provided by `sdc_devel` itself.

```php
use Drupal\Tests\sdc_devel\Kernel\SdcDevelComponentKernelTestBase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ComponentValidatorTest extends SdcDevelComponentKernelTestBase {

  protected static $modules = [
    'my_module_with_components',
  ];

  protected static $themes = [
    'my_theme_with_components',
  ];

}
```

Three static properties tune what is reported:

| Property | Default | Purpose |
| --- | --- | --- |
| `$levelReport` | `WARNING`, `ERROR`, `CRITICAL` | Severities that make the test fail. |
| `$excludeProvider` | `[]` | Providers to skip, by module or theme name. |
| `$excludeComponentId` | `[]` | Components to skip, by full plugin id. |

```php
protected static $levelReport = [
  RfcLogLevel::ERROR,
  RfcLogLevel::CRITICAL,
];

protected static $excludeComponentId = [
  'my_theme:legacy_card',
];
```

On failure the assertion message lists every component with its severity, message type,
line and tip. If no component is found at all, the test is skipped rather than passed.
