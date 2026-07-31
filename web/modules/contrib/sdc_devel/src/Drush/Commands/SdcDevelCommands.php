<?php

declare(strict_types=1);

namespace Drupal\sdc_devel\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\sdc_devel\Validator;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A Drush command to help devel a project UI Components.
 *
 * @codeCoverageIgnore
 */
final class SdcDevelCommands extends DrushCommands {
  use AutowireTrait;

  /**
   * Keep a list of installed projects for uninstall.
   */
  private array $installed = [];

  /**
   * Constructs an UiPatternsValidate object.
   */
  public function __construct(
    #[Autowire(service: 'plugin.manager.sdc')]
    private readonly ComponentPluginManager $componentPluginManager,
    #[Autowire(service: 'sdc_devel.validator')]
    private readonly Validator $validator,
    private readonly SiteAliasManagerInterface $siteAliasManager,
    private readonly ModuleExtensionList $extensionListModule,
    private readonly ThemeExtensionList $extensionListTheme,
    private readonly ThemeInstallerInterface $themeInstaller,
  ) {
    parent::__construct();
  }

  /**
   * Command to validate a project UI Components.
   *
   * @param string $project
   *   The project to validate.
   * @param string|null $id
   *   The specific component id to validate.
   * @param array $options
   *   Options for the command, including 'install'.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The validation results.
   */
  #[CLI\Command(name: 'sdc-devel:validate', aliases: ['sdcv'])]
  #[CLI\Argument(name: 'project', description: 'The project to validate, comma separated list for multiple projects.')]
  #[CLI\Argument(name: 'id', description: 'The specific component id to validate.')]
  #[CLI\Option(name: 'install', description: 'Install the project if needed.')]
  #[CLI\Usage(name: 'sdc-devel:validate my_project', description: 'Validate components for this project.')]
  #[CLI\Usage(name: 'sdc-devel:validate my_project component:id', description: 'Validate a specific component for this project.')]
  #[CLI\FieldLabels(labels: [
    'component' => 'Component',
    'severity' => 'Severity',
    'message' => 'Message',
    'type' => 'Type',
    'line' => 'Line',
    'source' => 'Source',
  ])]
  #[CLI\DefaultTableFields(fields: ['component', 'severity', 'message', 'Type', 'line', 'source'])]
  #[CLI\FilterDefaultField(field: 'component')]
  public function uiPatternsDevelValidate(string $project, ?string $id = NULL, array $options = ['install' => FALSE]): RowsOfFields {
    if (FALSE !== \strpos($project, ',')) {
      $projects = \explode(',', $project);
    }
    else {
      $projects = [$project];
    }

    if (TRUE === $options['install']) {
      $this->installProjects($projects);
    }

    $rows = [];
    foreach ($projects as $project) {
      $this->logger()->notice(dt('Start validation of @project...', ['@project' => $project]));
      $projectRows = $this->validate($project, $id);
      if ($projectRows === NULL) {
        $this->logger()->warning(dt('No components found, either it contains no components or is not enabled. Run this command with `--install` to try to force install.'));
      }
      elseif (empty($projectRows)) {
        $this->logger()->success(dt('No components errors found for @project, nice job! 👍', ['@project' => $project]));
      }
      else {
        $params = ['@count' => count($projectRows), '@project' => $project];
        $this->logger()->warning(dt('Found @count problems in @project.', $params));
        $rows += $projectRows;
      }
    }

    if (TRUE === $options['install']) {
      $this->uninstallProjects();
    }

    return new RowsOfFields($rows);
  }

  /**
   * Validate a project UI Components.
   *
   * @param string $project
   *   The project to validate.
   * @param string|null $id
   *   The specific component id to validate.
   *
   * @return array|null
   *   The validation results.
   */
  private function validate(string $project, ?string $id): ?array {
    $this->componentPluginManager->clearCachedDefinitions();
    $components = $this->componentPluginManager->getAllComponents();

    return $this->validateComponents($project, $components, $id);
  }

  /**
   * Validate components and collect validation messages.
   *
   * @param string $project
   *   The project to validate.
   * @param array $components
   *   The components to validate.
   * @param string|null $id
   *   The specific component id to validate.
   *
   * @return array|null
   *   The validation results as an array of rows.
   */
  private function validateComponents(string $project, array $components, ?string $id): ?array {
    $found_components = FALSE;
    $rows = [];
    foreach ($components as $component) {
      $provider = $component->getBaseId();
      if ($provider !== $project) {
        continue;
      }

      $found_components = TRUE;
      $component_id = $component->getPluginId();

      if ($id !== NULL && $id !== $component_id) {
        continue;
      }

      $this->validator->validate($component_id, $component);
      $messages = $this->validator->getMessagesSortedByGroupAndLine();
      $this->validator->resetMessages();

      $rows = \array_merge($rows, $this->formatMessages($component_id, $messages));
    }

    if (!$found_components) {
      return NULL;
    }

    return $rows;
  }

  /**
   * Format validation messages into rows.
   *
   * @param string $component_id
   *   The component id.
   * @param array $messages
   *   The validation messages.
   *
   * @return array
   *   The formatted messages as an array of rows.
   */
  private function formatMessages(string $component_id, array $messages): array {
    $levels = RfcLogLevel::getLevels();
    $rows = [];
    foreach ($messages as $message) {
      $level = $levels[$message->level()] ?? 'Unknown';
      $line = ($message->line() === 0) ? '-' : $message->line();
      $source = $message->getSourceCode();
      $rows[] = [
        'component' => $component_id,
        'severity' => $level,
        'message' => \trim(\chunk_split((string) $message->message())),
        'type' => $message->getType(),
        'line' => $line,
        'source' => \trim($source),
      ];
    }
    return $rows;
  }

  /**
   * Install a project, theme or module.
   *
   * @param array $projects
   *   The project names to install.
   */
  private function installProjects(array $projects): void {
    $modules = $this->extensionListModule->getList();
    $themes = $this->extensionListTheme->getList();

    $result = [];
    foreach ($projects as $project) {
      if (isset($modules[$project]) && $modules[$project]->status == 0) {
        $result['module'][] = $project;
      }
      elseif (isset($themes[$project]) && $themes[$project]->status == 0) {
        $result['theme'][] = $project;
      }
    }

    if (empty($result)) {
      throw new \Exception(dt('Can not find this project!'));
    }

    if (!empty($result['theme'])) {
      $this->themeInstaller->install($result['theme'], TRUE);
    }
    if (!empty($result['module'])) {
      $process = $this->processManager()->drush($this->siteAliasManager->getSelf(), PmCommands::INSTALL, $result['module']);
      $process->mustRun();
    }

    $this->installed = $result;
  }

  /**
   * Uninstall a project, theme or module.
   */
  private function uninstallProjects(): void {
    if (!empty($this->installed['theme'])) {
      $this->themeInstaller->uninstall($this->installed['theme']);
    }
    if (!empty($this->installed['module'])) {
      $process = $this->processManager()->drush($this->siteAliasManager->getSelf(), PmCommands::UNINSTALL, $this->installed['module']);
      $process->mustRun();
    }
  }

}
