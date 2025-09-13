<?php

declare(strict_types=1);

namespace DrevOps\VortexInstaller\Tests\Functional\Handlers;

use DrevOps\VortexInstaller\Prompts\Handlers\CiProvider;
use DrevOps\VortexInstaller\Prompts\Handlers\Timezone;
use DrevOps\VortexInstaller\Prompts\PromptManager;
use DrevOps\VortexInstaller\Tests\Functional\FunctionalTestCase;
use DrevOps\VortexInstaller\Utils\Env;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Timezone::class)]
class TimezoneInstallTest extends AbstractInstallTestCase {

  public static function dataProviderInstall(): array {
    return [

      'timezone, gha' => [
        static::cw(function (): void {
          Env::put(PromptManager::makeEnvName(Timezone::id()), 'America/New_York');
          Env::put(PromptManager::makeEnvName(CiProvider::id()), CiProvider::GITHUB_ACTIONS);
        }),
        static::cw(function (FunctionalTestCase $test): void {
          // Timezone should be replaced in .env file.
          $test->assertFileContainsString(static::$sut . '/.env', 'TZ=America/New_York');
          $test->assertFileNotContainsString(static::$sut . '/.env', 'UTC');

          // Timezone should be replaced in Renovate config.
          $test->assertFileContainsString(static::$sut . '/renovate.json', '"timezone": "America/New_York"');
          $test->assertFileNotContainsString(static::$sut . '/renovate.json', 'UTC');

          // Timezone should not be replaced in GHA config in code as it should
          // be overridden via UI.
          $test->assertFileNotContainsString(static::$sut . '/.github/workflows/build-test-deploy.yml', 'America/New_York');
          $test->assertFileContainsString(static::$sut . '/.github/workflows/build-test-deploy.yml', 'UTC');

          // Timezone should not be replaced in Docker Compose config.
          $test->assertFileNotContainsString(static::$sut . '/docker-compose.yml', 'America/New_York');
          $test->assertFileContainsString(static::$sut . '/docker-compose.yml', 'UTC');
        }),
      ],

      'timezone, circleci' => [
        static::cw(function (): void {
          Env::put(PromptManager::makeEnvName(Timezone::id()), 'America/New_York');
          Env::put(PromptManager::makeEnvName(CiProvider::id()), CiProvider::CIRCLECI);
        }),
        static::cw(function (FunctionalTestCase $test): void {
          // Timezone should not be replaced in CircleCI config in code as it
          // should be overridden via UI.
          $test->assertFileNotContainsString(static::$sut . '/.circleci/config.yml', 'TZ: America/New_York');
          $test->assertFileContainsString(static::$sut . '/.circleci/config.yml', 'TZ: UTC');
        }),
      ],
    ];
  }

}
