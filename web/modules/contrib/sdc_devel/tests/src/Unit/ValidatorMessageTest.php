<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_devel\ValidatorMessage;
use Drupal\Tests\UnitTestCase;

/**
 * Simple test for validator message class.
 *
 * @coversDefaultClass \Drupal\sdc_devel\ValidatorMessage
 *
 * @group sdc_devel
 * @internal
 */
class ValidatorMessageTest extends UnitTestCase {

  /**
   * @covers ::getType
   */
  public function testGetTypeForTwig(): void {
    $message = new TranslatableMarkup('Test message');
    $message = ValidatorMessage::createForTwigString('test', $message);

    $expected = new TranslatableMarkup('Twig', [], ['context' => 'sdc_devel']);
    $this->assertEquals($expected, $message->getType());
  }

  /**
   * @covers ::getType
   */
  public function testGetTypeForSchema(): void {
    $message = new TranslatableMarkup('Test message');
    $message = ValidatorMessage::createForString('test', $message, 3, 0, 0);

    $expected = new TranslatableMarkup('Schema', [], ['context' => 'sdc_devel']);
    $this->assertEquals($expected, $message->getType());
  }

  /**
   * @covers ::getSourceCode
   *
   * @dataProvider sourceCodeProvider
   */
  public function testGetSourceCode(string $id, TranslatableMarkup $message, int $level, int $line, int $length, ?string $source, string $expected): void {
    $validatorMessage = ValidatorMessage::createForString($id, $message, $level, $line, $length, $source);
    $this->assertSame($expected, $validatorMessage->getSourceCode());
  }

  /**
   * Data provider for testGetSourceCode.
   */
  public static function sourceCodeProvider(): array {
    return [
      'null source' => [
        'test_id', new TranslatableMarkup('Test message'), 1, 1, 1, NULL, '',
      ],
      'single line' => [
        'test_id', new TranslatableMarkup('Test message'), 1, 2, 1, "line1\nline2\nline3", 'line2',
      ],
      'multiple lines' => [
        'test_id', new TranslatableMarkup('Test message'), 1, 2, 2, "line1\nline2\nline3\nline4", "line2\nline3",
      ],
      'out of bounds line' => [
        'test_id', new TranslatableMarkup('Test message'), 1, 5, 1, "line1\nline2\nline3", '',
      ],
    ];
  }

}
