<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_tools\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\migrate_tools\MigrateTools;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the MigrateTools helper.
 */
#[CoversClass(MigrateTools::class)]
#[Group('migrate_tools')]
final class MigrateToolsTest extends UnitTestCase {

  /**
   * Tests building the ID list from the command options.
   */
  #[DataProvider('dataProviderIdList')]
  public function testBuildIdList(array $options, array $expected): void {
    $results = MigrateTools::buildIdList($options);
    $this->assertEquals($results, $expected);
  }

  /**
   * Data provider for testBuildIdList.
   */
  public static function dataProviderIdList(): array {
    $cases = [];
    $cases[] = [
      'options' => [],
      'expected' => [],
    ];
    $cases['single id'] = [
      'options' => [
        'idlist' => 123,
      ],
      'expected' => [[123]],
    ];
    $cases['multiple ids'] = [
      'options' => [
        'idlist' => '123, 456',
      ],
      'expected' => [
        [123], [456],
      ],
    ];
    $cases['default delimiter, composite key'] = [
      'options' => [
        'idlist' => '123:456',
      ],
      'expected' => [
        [123, 456],
      ],
    ];
    $cases['special delimiter, single'] = [
      'options' => [
        'idlist' => '123:456',
        'idlist-delimiter' => '~',
      ],
      'expected' => [
        ['123:456'],
      ],
    ];
    $cases['special delimiter, multiple'] = [
      'options' => [
        'idlist' => '123:456~987:654',
        'idlist-delimiter' => '~',
      ],
      'expected' => [
        ['123:456', '987:654'],
      ],
    ];
    $cases['space delimiter, multiple'] = [
      'options' => [
        'idlist' => '123:456 987:654',
        'idlist-delimiter' => ' ',
      ],
      'expected' => [
        ['123:456', '987:654'],
      ],
    ];
    return $cases;
  }

}
