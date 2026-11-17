<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_tools\Unit\Routing;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate_tools\Routing\RouteProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Routing\Route;

/**
 * Tests the RouteProcessor class.
 */
#[CoversClass(RouteProcessor::class)]
#[Group('migrate_tools')]
final class RouteProcessorTest extends UnitTestCase {

  /**
   * Tests processOutbound when the migration parameter is missing.
   *
   * This ensures that we do not attempt to load a migration entity with a NULL
   * ID, which would cause an assertion error.
   */
  public function testProcessOutboundWithMissingMigrationParameter(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasHandler')
      ->with('migration', 'storage')
      ->willReturn(TRUE);

    $entityTypeManager->expects($this->never())
      ->method('getStorage');

    $processor = new RouteProcessor($entityTypeManager);

    $route = new Route('/test', ['_migrate_group' => TRUE]);
    $parameters = [];
    $processor->processOutbound('test_route', $route, $parameters);
    $this->assertSame('default', $parameters['migration_group']);
  }

}
