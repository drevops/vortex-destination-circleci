## [1.0.3] - 2026-08-03

### 🚀 Features

- [#3517321](https://www.drupal.org/project/sdc_devel/issues/3517321) Change visibility of the rules constants in TwigValidatorRulePluginBase
- [#3580788](https://www.drupal.org/project/sdc_devel/issues/3580788) Add support for html_cva()
- Add documentation and configuration files for SDC Devel
- Add manual rule to GitLab CI configuration

### 🐛 Bug Fixes

- [#3591124](https://www.drupal.org/project/sdc_devel/issues/3591124) Automated Drupal 12 compatibility fixes for sdc_devel 1.0.x-dev
- Correct spelling of 'validate' in ValidatorTest and update wording in rules documentation

### 💼 Other

- [#3591123](https://www.drupal.org/project/sdc_devel/issues/3591123) Handle form_state for form components
## [1.0.2] - 2026-01-09

### 🚀 Features

- Provide kernel test base class
## [1.0.1] - 2025-03-08

### 🐛 Bug Fixes

- Avoid slot type warning for tests not related
- Better drush command messages when not enabled or no components to validate

### 💼 Other

- Resolve [#3509096](https://www.drupal.org/project/sdc_devel/issues/3509096) "Fix issue logicexception"
- [#3511691](https://www.drupal.org/project/sdc_devel/issues/3511691) by mogtofu33: Restrict version based on Twig version
- [#3511772](https://www.drupal.org/project/sdc_devel/issues/3511772) by mogtofu33: Ternary and default tests update with Twig 3.19
- [#3511259](https://www.drupal.org/project/sdc_devel/issues/3511259) by liam morland, mogtofu33: Warning is raised about null ternary even when it is not used
- [#3502526](https://www.drupal.org/project/sdc_devel/issues/3502526) by pdureau, mogtofu33: Remove the warning about do tag?
- [#3511773](https://www.drupal.org/project/sdc_devel/issues/3511773) by pdureau, mogtofu33: Raise a warning is a slot is typed

### 🎨 Styling

- Fix some styling issues

### 🧪 Testing

- Fix an equal test

### ⚙️ Miscellaneous Tasks

- Back to last available Drupal version
## [1.0.0] - 2025-01-24

### 🐛 Bug Fixes

- *(https://www.drupal.org/project/sdc_devel/issues/3499164)* Automated Drupal 11 compatibility fixes for sdc_devel
- Return too early on variable set
- Minor rewrite to simplify, fix not needed critical

### 💼 Other

- Initial commit, moved from ui_patterns_devel.
- [#3492533](https://www.drupal.org/project/sdc_devel/issues/3492533) by pdureau, mogtofu33: Remove remaining references to UI Patterns
- [#3485967](https://www.drupal.org/project/sdc_devel/issues/3485967) by pdureau, mogtofu33: Validator: warning if `..` operator is found
- [#3490871](https://www.drupal.org/project/sdc_devel/issues/3490871) by pdureau, rajab natshah, mogtofu33: include() function must...
- [#3499872](https://www.drupal.org/project/sdc_devel/issues/3499872) by pdureau, mogtofu33: Remove the warning about required props
- [#3499867](https://www.drupal.org/project/sdc_devel/issues/3499867) by pdureau, mogtofu33: [regression?] macro parameters are missing from declared variable
- [#3461538](https://www.drupal.org/project/sdc_devel/issues/3461538) by pdureau, mogtofu33: Twig validator: Shorter IF/FOR syntax
- [#3461538](https://www.drupal.org/project/sdc_devel/issues/3461538) by pdureau, mogtofu33: Twig validator: Shorter IF/FOR syntax, check only if the if is for the same variable

### 🎨 Styling

- Cspell, phpcs and test dependency fix
- Sort classes properly
- Fix qa
- Fix minor style issues

### 🧪 Testing

- Fix and update tests when ui_patterns is not installed
