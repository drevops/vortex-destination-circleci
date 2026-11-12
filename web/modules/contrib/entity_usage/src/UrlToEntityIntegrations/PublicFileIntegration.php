<?php

declare(strict_types=1);

namespace Drupal\entity_usage\UrlToEntityIntegrations;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Drupal\entity_usage\SiteDomains;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Determines if the URL points to a public file managed as a file entity.
 */
class PublicFileIntegration implements EventSubscriberInterface {

  /**
   * The regex pattern to match requests to the public files directory.
   */
  private string $publicFilePattern;

  /**
   * The external URL of the public files directory.
   *
   * @var string
   */
  private string $externalUrl;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'stream_wrapper.public')]
    StreamWrapperInterface $publicStream,
    SiteDomains $siteDomains,
  ) {
    $this->externalUrl = rtrim(mb_strtolower($publicStream->getExternalUrl()), '/');
    if ($publicStream instanceof LocalStream) {
      $internal_url = $siteDomains->getInternalUrl($this->externalUrl);
      if (is_string($internal_url) && strlen($internal_url) > 0) {
        $this->publicFilePattern = '{^' . preg_quote(rtrim($internal_url, '/'), '{}') . '/}';
      }
      else {
        throw new \LogicException('The public stream wrapper does not provide a valid external URL.');
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [Events::URL_TO_ENTITY => ['getFileFromPath', 500]];
  }

  /**
   * Determines if the URL points to a public file managed as a file entity.
   *
   * @param \Drupal\entity_usage\Events\UrlToEntityEvent $event
   *   The event.
   */
  public function getFileFromPath(UrlToEntityEvent $event): void {
    if (!$event->isEntityTypeTracked('file')) {
      return;
    }

    if (str_starts_with($event->unprocessedUrl, $this->externalUrl . '/')) {
      $file_uri = 'public://' . ltrim(urldecode(substr($event->unprocessedUrl, strlen($this->externalUrl))), '/');
    }

    if (!isset($file_uri) && isset($this->publicFilePattern)) {
      $url = $event->getRequest()->getPathInfo();
      if (preg_match($this->publicFilePattern, $url)) {
        // Check if we can map the link to a public file.
        $file_uri = preg_replace($this->publicFilePattern, 'public://', urldecode($url));
      }
    }
    if (isset($file_uri)) {
      $files = $this->entityTypeManager->getStorage('file')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('uri', $file_uri)
        ->range(0, 1)
        ->execute();
      if (!empty($files)) {
        // File entity found.
        $event->setEntityInfo('file', reset($files));
      }
    }
  }

}
