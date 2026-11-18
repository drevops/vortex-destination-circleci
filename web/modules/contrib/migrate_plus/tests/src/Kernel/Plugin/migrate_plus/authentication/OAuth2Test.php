<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\authentication;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate_plus\Plugin\migrate_plus\authentication\OAuth2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the oauth2 authentication plugin deprecation.
 */
#[CoversClass(OAuth2::class)]
#[Group('migrate_plus')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
final class OAuth2Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate_plus', 'migrate'];

  /**
   * Tests that instantiating OAuth2 triggers a deprecation notice.
   */
  public function testDeprecationOnInstantiation(): void {
    $this->expectUserDeprecationMessage('The "oauth2" authentication plugin is deprecated in migrate_plus:6.0.11 and is removed from migrate_plus:7.0.0. Use the "oauth2_client" plugin instead. See https://www.drupal.org/project/migrate_plus/issues/2879665');
    new OAuth2([], 'oauth2', []);
  }

}
