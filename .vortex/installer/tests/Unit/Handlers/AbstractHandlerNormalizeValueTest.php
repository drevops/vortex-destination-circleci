<?php

declare(strict_types=1);

namespace DrevOps\VortexInstaller\Tests\Unit\Handlers;

use DrevOps\VortexInstaller\Prompts\Handlers\AbstractHandler;
use DrevOps\VortexInstaller\Prompts\Handlers\DeployTypes;
use DrevOps\VortexInstaller\Prompts\Handlers\Modules;
use DrevOps\VortexInstaller\Prompts\Handlers\Name;
use DrevOps\VortexInstaller\Prompts\Handlers\NotificationChannels;
use DrevOps\VortexInstaller\Prompts\Handlers\Services;
use DrevOps\VortexInstaller\Prompts\PromptManager;
use DrevOps\VortexInstaller\Tests\Unit\UnitTestCase;
use DrevOps\VortexInstaller\Utils\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for AbstractHandler::normalizeValue().
 */
#[CoversClass(AbstractHandler::class)]
class AbstractHandlerNormalizeValueTest extends UnitTestCase {

  #[DataProvider('dataProviderNormalizeValue')]
  public function testNormalizeValue(string $handler_id, mixed $input, mixed $expected): void {
    $config = Config::fromString('{}');
    $prompt_manager = new PromptManager($config);
    $handlers = $prompt_manager->getHandlers();

    $this->assertArrayHasKey($handler_id, $handlers);

    $handler = $handlers[$handler_id];
    $this->assertSame($expected, $handler->normalizeValue($input));
  }

  public static function dataProviderNormalizeValue(): \Iterator {
    // MultiSelect: comma-separated string converted to array.
    yield 'modules - comma string' => [Modules::id(), 'admin_toolbar,coffee', ['admin_toolbar', 'coffee']];
    // MultiSelect: single string converted to single-element array.
    yield 'modules - single string' => [Modules::id(), 'admin_toolbar', ['admin_toolbar']];
    // MultiSelect: array passed through unchanged.
    yield 'modules - array' => [Modules::id(), ['admin_toolbar', 'coffee'], ['admin_toolbar', 'coffee']];
    // MultiSelect: empty string converted to empty array.
    yield 'modules - empty string' => [Modules::id(), '', []];

    // Other multiselect handlers.
    yield 'services - single string' => [Services::id(), 'redis', ['redis']];
    yield 'services - comma string' => [Services::id(), 'redis,solr', ['redis', 'solr']];
    yield 'deploy_types - single string' => [DeployTypes::id(), 'lagoon', ['lagoon']];
    yield 'notification_channels - single string' => [NotificationChannels::id(), 'email', ['email']];

    // Text handler: string passed through unchanged.
    yield 'name - string passthrough' => [Name::id(), 'My Project', 'My Project'];
    // Text handler: array passed through unchanged (not its concern).
    yield 'name - array passthrough' => [Name::id(), ['a'], ['a']];
  }

}
