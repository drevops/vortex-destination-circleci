<?php

declare(strict_types=1);

namespace Drupal\Tests\stage_file_proxy\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\stage_file_proxy\Controller\ImageStyleDownloadController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests WebP support integration with stage_file_proxy module.
 */
#[Group('stage_file_proxy')]
#[RunTestsInSeparateProcesses]
class StageFileProxyWebpTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'file',
    'image',
    'stage_file_proxy',
    'imageapi_optimize_webp',
    'user',
    'field',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'image']);

    // Create a basic image style for testing.
    ImageStyle::create([
      'name' => 'test_style',
      'label' => 'Test Style',
    ])->save();
  }

  /**
   * Tests that a WebP image style request is handled without a fatal error.
   */
  public function testWebpRequestProcessing(): void {
    $request = Request::create('/styles/test_style/public/test.webp');
    $image_style = ImageStyle::load('test_style');
    $controller = ImageStyleDownloadController::create($this->container);

    // Exception should be thrown if the file is not found.
    // Which is good shows it made it to that code.
    $this->expectException(NotFoundHttpException::class);
    $controller->deliver($request, 'public', $image_style);
  }

}
