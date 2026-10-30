<?php

declare(strict_types=1);

namespace Drupal\Tests\stage_file_proxy\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Drupal\stage_file_proxy\Drush\Commands\StageFileProxyCommands;
use Drush\Log\DrushLoggerManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Tests the stage_file_proxy:dl Drush command.
 */
#[CoversClass(StageFileProxyCommands::class)]
#[Group('stage_file_proxy')]
#[RunTestsInSeparateProcesses]
class StageFileProxyCommandsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
  }

  /**
   * Creates managed files that do not exist on disk.
   *
   * @param int $count
   *   The number of files to create.
   */
  protected function createManagedFiles(int $count): void {
    for ($i = 0; $i < $count; $i++) {
      File::create([
        'uri' => "public://interval-test-$i.txt",
      ])->save();
    }
  }

  /**
   * Creates a command instance with a mocked config and download manager.
   *
   * @param \Drupal\stage_file_proxy\DownloadManagerInterface $downloadManager
   *   The download manager to use.
   *
   * @return \Drupal\stage_file_proxy\Drush\Commands\StageFileProxyCommands
   *   The command instance, ready to run.
   */
  protected function createCommand(DownloadManagerInterface $downloadManager): StageFileProxyCommands {
    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['origin', 'https://example.com'],
      ['origin_dir', ''],
      ['verify', TRUE],
    ]);

    $configFactory = $this->createStub(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('stage_file_proxy.settings')
      ->willReturn($config);

    $command = new StageFileProxyCommands(
      $configFactory,
      $this->container->get('database'),
      $downloadManager,
      $this->container->getParameter('app.root'),
    );
    $command->setOutput(new NullOutput());
    $command->setLogger(new DrushLoggerManager());

    return $command;
  }

  /**
   * Tests that the interval option delays each download.
   */
  public function testIntervalDelaysDownloads(): void {
    $this->createManagedFiles(3);

    $timestamps = [];
    $downloadManager = $this->createStub(DownloadManagerInterface::class);
    $downloadManager->method('filePublicPath')->willReturn('sites/default/files');
    $downloadManager->method('fetch')->willReturnCallback(function () use (&$timestamps) {
      $timestamps[] = microtime(TRUE);
      return TRUE;
    });

    $command = $this->createCommand($downloadManager);
    $command->dl([
      'skip-progress-bar' => TRUE,
      'fid' => 0,
      'interval' => 100,
    ]);

    $this->assertCount(3, $timestamps);
    for ($i = 1; $i < count($timestamps); $i++) {
      $gap = $timestamps[$i] - $timestamps[$i - 1];
      $this->assertGreaterThanOrEqual(0.09, $gap, 'Downloads should be spaced by roughly the configured interval.');
    }
  }

  /**
   * Tests that no delay occurs when the interval option is not set.
   */
  public function testNoIntervalDoesNotDelayDownloads(): void {
    $this->createManagedFiles(5);

    $timestamps = [];
    $downloadManager = $this->createStub(DownloadManagerInterface::class);
    $downloadManager->method('filePublicPath')->willReturn('sites/default/files');
    $downloadManager->method('fetch')->willReturnCallback(function () use (&$timestamps) {
      $timestamps[] = microtime(TRUE);
      return TRUE;
    });

    $command = $this->createCommand($downloadManager);
    $start = microtime(TRUE);
    $command->dl([
      'skip-progress-bar' => TRUE,
      'fid' => 0,
      'interval' => 0,
    ]);
    $elapsed = microtime(TRUE) - $start;

    $this->assertCount(5, $timestamps);
    $this->assertLessThan(0.5, $elapsed, 'Without an interval, downloads should not be artificially delayed.');
  }

}
