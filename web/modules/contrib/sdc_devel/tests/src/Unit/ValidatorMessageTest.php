<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sdc_devel\ValidatorMessage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Simple test for validator message class.
 *
 * @internal
 */
#[CoversClass(ValidatorMessage::class)]
#[Group('sdc_devel')]
final class ValidatorMessageTest extends UnitTestCase {

  public function testGetTypeForTwig(): void {
    $message = new TranslatableMarkup('Test message');
    $message = ValidatorMessage::createForTwigString('test', $message);

    $expected = new TranslatableMarkup('Twig', [], ['context' => 'sdc_devel']);
    self::assertEquals($expected, $message->getType());
  }

  public function testGetTypeForSchema(): void {
    $message = new TranslatableMarkup('Test message');
    $message = ValidatorMessage::createForString('test', $message, 3, 0, 0);

    $expected = new TranslatableMarkup('Schema', [], ['context' => 'sdc_devel']);
    self::assertEquals($expected, $message->getType());
  }

  #[DataProvider('sourceCodeProvider')]
  public function testGetSourceCode(string $id, TranslatableMarkup $message, int $level, int $line, int $length, ?string $source, string $expected): void {
    $validatorMessage = ValidatorMessage::createForString($id, $message, $level, $line, $length, $source);
    self::assertSame($expected, $validatorMessage->getSourceCode());
  }

  /**
   * Provides tests data for testGetSourceCode.
   *
   * @return array
   *   An array of test data:
   *   - component id
   *   - Message to test
   *   - level number
   *   - line number
   *   - length number
   *   - source text
   *   - array of error line and levels expected.
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
