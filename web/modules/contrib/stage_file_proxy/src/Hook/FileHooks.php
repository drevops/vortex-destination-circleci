<?php

declare(strict_types=1);

namespace Drupal\stage_file_proxy\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\stage_file_proxy\DownloadManagerInterface;

/**
 * File hooks for the stage_file_proxy module.
 */
class FileHooks {

  public function __construct(
    protected readonly DownloadManagerInterface $downloadManager,
  ) {}

  /**
   * Implements hook_file_url_alter().
   */
  #[Hook('file_url_alter')]
  public function alterFileUrl(&$uri): void {
    if ($result = $this->downloadManager->rewriteFileUrl($uri)) {
      $uri = $result;
    }
  }

}
