# SDC Devel

Provides development aids to [Single-Directory Components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components) developers.

SDC Devel reads your components without rendering them. It checks the `*.component.yml`
definition against the SDC schema, then parses the Twig template into its syntax tree and
walks it looking for anything that breaks a component contract: application state leaking
into a template, forbidden filters, variables that are never printed, and so on.

Nothing is written and nothing is rendered, so it is safe to run on any project.

## Features

- Override some core Components fatal errors to allow better debugging.
- Validate component YAML file with details.
- Statically check the syntax of Twig templates for errors and best practices.

## Why

A component is meant to be a sandbox. It receives props and slots, and renders markup.
Once a template calls `path()`, `active_theme()` or `|render`, it depends on the Drupal
application around it, and it stops being portable, testable or usable in isolation.

Those calls are still valid Twig, so nothing warns you. SDC Devel does.

## Requirements

- Drupal `^10.3.12 || ^11.0.11 || ^11.1.2 || ^12`
- `twig/twig` `~3.19`

## Installation

```bash
composer require drupal/sdc_devel
drush en sdc_devel
```

This is a development module. Do not install it on production.

## Where to go next

- [Usage](usage.md): the report page, the Drush command, and the kernel test base.
- [Twig rules](rules.md): what is checked, and with which severity.
- [Extending](extending.md): write your own rule plugin.
