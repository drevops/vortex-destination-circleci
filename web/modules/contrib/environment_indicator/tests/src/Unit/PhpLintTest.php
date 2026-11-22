<?php

namespace Drupal\Tests\environment_indicator\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests that all PHP files are valid.
 *
 * @coversNothing
 * @group environment_indicator
 */
class PhpLintTest extends TestCase {

  /**
   * Files or directories to lint, relative to DRUPAL_ROOT.
   *
   * @return array[]
   *   An array of arrays, each containing a path to a file or directory.
   */
  public static function lintTargets(): array {
    return [
      [
        'modules/custom/environment_indicator',
      ],
    ];
  }

  /**
   * Tests that all PHP files are valid.
   *
   * @param string $relativePath
   *   The path to the file or directory to lint, relative to DRUPAL_ROOT.
   *
   * @dataProvider lintTargets
   *
   * @throws \PHPUnit\Framework\ExpectationFailedException
   *   If the assertion fails.
   */
  public function testPhpLint(string $relativePath): void {
    $fullPath = DRUPAL_ROOT . '/' . $relativePath;
    $this->assertFileExists($fullPath, "Expected path to exist: $relativePath");

    $paths = is_dir($fullPath)
      ? $this->findPhpFiles($fullPath)
      : [$fullPath];

    foreach ($paths as $file) {
      exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
      $this->assertSame(
        0,
        $code,
        sprintf(
          "PHP parse error in %s:\n%s",
          substr($file, strlen(DRUPAL_ROOT) + 1),
          implode("\n", $output)
        )
      );
    }
  }

  /**
   * Recursively find all .php files under a directory.
   *
   * @param string $dir
   *   The directory to search.
   *
   * @return array
   *   An array of file paths.
   */
  protected function findPhpFiles(string $dir): array {
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    $files = [];
    foreach ($iterator as $f) {
      if ($f->isFile() && $f->getExtension() === 'php') {
        $files[] = $f->getPathname();
      }
    }
    return $files;
  }

}
