<?php

declare(strict_types=1);

namespace Drupal\entity_usage;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wraps the site domains configuration.
 *
 * Provides a BC wrapper and helper methods for the site domains configuration.
 */
readonly final class SiteDomains {

  /**
   * The list of domains information considered to be part of the site.
   *
   * @var list<array{host:string, path:string}>
   */
  public array $list;

  /**
   * The site subdirectory if it is installed in one.
   *
   * @var string
   */
  public string $subPath;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    RequestContext $requestContext,
    #[Autowire(service: 'stream_wrapper.public')]
    StreamWrapperInterface $publicStream,
  ) {
    $site_domains = $configFactory->get('entity_usage.settings')->get('site_domains') ?? [];

    // If an update fires before entity_usage_update_8301() then the site
    // domains configuration will be an array of strings.
    if (!empty($site_domains) && !is_array(reset($site_domains))) {
      $site_domains = self::stringsToConfig($site_domains);
    }

    // Auto-discover the site base URL from the current request context.
    $base_url = rtrim(mb_strtolower($requestContext->getCompleteBaseUrl()), '/');
    foreach (self::stringsToConfig([$base_url]) as $entry) {
      if (!in_array($entry, $site_domains, TRUE)) {
        $site_domains[] = $entry;
      }
    }

    $sub_path = '';
    foreach ($site_domains as $site_domain_info) {
      $utf8_host = idn_to_utf8($site_domain_info['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
      if ($utf8_host !== FALSE && $utf8_host !== $site_domain_info['host']) {
        $site_domains[] = [
          'host' => $utf8_host,
          'path' => $site_domain_info['path'],
        ];
      }
      // If Drupal is installed in a subdirectory, we need to remove it from
      // relative URLs. Assume we only have one base path to think about.
      if ($sub_path === '' && $site_domain_info['path'] !== '') {
        $sub_path = $site_domain_info['path'];
      }
    }

    // Auto-discover the public stream wrapper URL. This runs after subPath
    // detection so that a CDN or S3 path is never treated as the site's
    // subdirectory prefix.
    if (!($publicStream instanceof LocalStream)) {
      $external_url = rtrim(mb_strtolower($publicStream->getExternalUrl()), '/');
      foreach (self::stringsToConfig([$external_url]) as $entry) {
        if (!in_array($entry, $site_domains, TRUE)) {
          $site_domains[] = $entry;
          $utf8_host = idn_to_utf8($entry['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
          if ($utf8_host !== FALSE && $utf8_host !== $entry['host']) {
            $site_domains[] = ['host' => $utf8_host, 'path' => $entry['path']];
          }
        }
      }
    }

    $this->list = $site_domains;
    $this->subPath = $sub_path;
  }

  /**
   * Removes the domain from the url if it is considered to be part of the site.
   *
   * @param string $url
   *   A relative or absolute URL string. URLs are case-insensitive in Drupal it
   *   is up to the caller to ensure that the URL is in lowercase. Use
   *   mb_strtolower().
   *
   * @return string|null
   *   A relative URL string or NULL if the url is not considered to be part of
   *   the site.
   */
  public function getInternalUrl(string $url): ?string {
    if (UrlHelper::isExternal($url)) {
      // Strip off the scheme and host, so we only get the path.
      foreach ($this->list as $site_domain_info) {
        if (str_contains($url, $site_domain_info['host'])) {
          // Strip off everything that is not the internal path.
          $parsed_url = parse_url($url);
          if (isset($parsed_url['host']) && $parsed_url['host'] === $site_domain_info['host']) {
            if ($site_domain_info['path'] === '') {
              return $parsed_url['path'] ?? '/';
            }
            elseif (isset($parsed_url['path']) && (str_starts_with($parsed_url['path'], $site_domain_info['path'] . '/') || $parsed_url['path'] === $site_domain_info['path'])) {
              $path = substr($parsed_url['path'], strlen($site_domain_info['path']));
              return $path === '' ? '/' : $path;
            }
          }
        }
      }

      return NULL;
    }
    elseif ($this->subPath !== '' && str_starts_with($url, $this->subPath . '/')) {
      return substr($url, strlen($this->subPath));
    }
    return $url;
  }

  /**
   * Converts the old site domains configuration to the new format.
   *
   * @param string[] $site_domains_as_string
   *   The site domains configuration as an array of strings.
   *
   * @return list<array{host:string, path:string}>
   *   The site domains configuration.
   */
  public static function stringsToConfig(array $site_domains_as_string): array {
    $site_domains = [];
    foreach ($site_domains_as_string as $domain) {
      $domain = mb_strtolower($domain);
      if (!preg_match('#^https?://#', $domain)) {
        $domain = 'http://' . $domain;
      }
      $url = parse_url($domain);
      if (empty($url['host'])) {
        continue;
      }
      $host = idn_to_ascii($url['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
      if ($host === FALSE) {
        continue;
      }
      $site_domains[] = [
        'host' => $host,
        'path' => $url['path'] ?? '',
      ];
    }
    return $site_domains;
  }

}
