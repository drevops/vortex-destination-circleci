<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Attribute\Authentication;
use Drupal\migrate_plus\AuthenticationPluginBase;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Provides OAuth2 authentication for the HTTP resource.
 *
 * @link https://github.com/thephpleague/oauth2-client
 *
 * @code {.yml}
 *   source:
 *     plugin: url
 *     data_fetcher_plugin: http
 *     data_parser_plugin: json
 *     urls: https://api.example.com/resource/path
 *     authentication:
 *       plugin: oauth2_client
 *       grant_type: client_credentials
 *       provider_options:
 *         clientId: <client-id>
 *         clientSecret: <client-secret>
 *         urlAuthorize: https://login.example.com/oauth2/authorize
 *         urlResourceOwnerDetails: https://login.example.com/oauth2/resource
 *         urlAccessToken: https://login.example.com/oauth2/token
 *       grant_options:
 *         scope: https://api.example.com/
 *         resource: https://api.example.com
 *
 * @endcode
 */
#[Authentication(
  id: 'oauth2_client',
  title: new TranslatableMarkup('OAuth2')
)]
class OAuth2Client extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationOptions($url): array {
    $provider_options = $this->configuration['provider_options'] ?? [];
    $grant_options = $this->configuration['grant_options'] ?? [];
    $grant_type = $this->configuration['grant_type'] ?? NULL;

    if (!is_array($provider_options) || empty($provider_options)) {
      throw new MigrateException('Missing or invalid "provider_options" configuration.');
    }
    if (!is_string($grant_type) || empty($grant_type)) {
      throw new MigrateException('Missing or invalid "grant_type" configuration.');
    }

    $provider = new GenericProvider($provider_options);
    $access_token = $provider->getAccessToken($grant_type, $grant_options);
    return [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token->getToken(),
      ],
    ];
  }

}
