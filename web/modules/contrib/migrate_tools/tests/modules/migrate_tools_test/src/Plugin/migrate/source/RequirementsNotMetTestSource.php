<?php

declare(strict_types=1);

namespace Drupal\migrate_tools_test\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\migrate\source\EmptySource;
use Drupal\migrate\Plugin\RequirementsInterface;

/**
 * A migrate source whose requirements are never met.
 */
#[MigrateSource(id: 'migrate_tools_requirements_not_met')]
final class RequirementsNotMetTestSource extends EmptySource implements RequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): void {
    throw new RequirementsException('Never met.');
  }

}
