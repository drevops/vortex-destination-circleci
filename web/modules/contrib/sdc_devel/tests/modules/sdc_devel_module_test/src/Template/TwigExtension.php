<?php

declare(strict_types=1);

namespace Drupal\sdc_devel_module_test\Template;

// cspell:ignore kint
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension mocking for missing functions or filters.
 */
final class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   *
   * @todo check if we really still mock third party functions.
   *
   * @return array
   *   The functions list.
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('help_route_link', [$this, 'mock']),
      new TwigFunction('help_topic_link', [$this, 'mock']),
      new TwigFunction('component', [$this, 'mock']),
      new TwigFunction('pattern', [$this, 'mock']),
      new TwigFunction('pattern_preview', [$this, 'mock']),
      new TwigFunction('component_story', [$this, 'mock']),
      new TwigFunction('devel_dump', [$this, 'mock']),
      new TwigFunction('kpr', [$this, 'mock']),
      new TwigFunction('kint', [$this, 'mock']),
      new TwigFunction('devel_message', [$this, 'mock']),
      new TwigFunction('dpm', [$this, 'mock']),
      new TwigFunction('dsm', [$this, 'mock']),
      new TwigFunction('country_names', [$this, 'mock']),
      new TwigFunction('country_timezones', [$this, 'mock']),
      new TwigFunction('currency_names', [$this, 'mock']),
      new TwigFunction('html_classes', [$this, 'mock']),
      new TwigFunction('language_names', [$this, 'mock']),
      new TwigFunction('locale_names', [$this, 'mock']),
      new TwigFunction('script_names', [$this, 'mock']),
      new TwigFunction('template_from_string', [$this, 'mock']),
      new TwigFunction('timezone_names', [$this, 'mock']),
      new TwigFunction('wp_dump', [$this, 'mock']),
      new TwigFunction('query_type', [$this, 'mock']),
      new TwigFunction('query_executable', [$this, 'mock']),
      new TwigFunction('icon', [$this, 'mock']),
    ];
  }

  /**
   * Mock function.
   */
  public function mock(): array {
    return ['#markup' => 'Mocked function by SDC Devel.'];
  }

}
