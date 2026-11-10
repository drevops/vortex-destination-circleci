<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\authentication;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Attribute\Authentication;
use Drupal\migrate_plus\AuthenticationPluginBase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Sainsburys\Guzzle\Oauth2\GrantType\AuthorizationCode;
use Sainsburys\Guzzle\Oauth2\GrantType\ClientCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\JwtBearer;
use Sainsburys\Guzzle\Oauth2\GrantType\PasswordCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\RefreshToken;
use Sainsburys\Guzzle\Oauth2\Middleware\OAuthMiddleware;

/**
 * Provides OAuth2 authentication for the HTTP resource.
 *
 * @deprecated in migrate_plus:6.0.11 and is removed from migrate_plus:7.0.0.
 * Use \Drupal\migrate_plus\Plugin\migrate_plus\authentication\OAuth2Client
 * instead.
 * @see https://www.drupal.org/project/migrate_plus/issues/2879665
 *
 * @link https://packagist.org/packages/sainsburys/guzzle-oauth2-plugin
 */
#[Authentication(
  id: 'oauth2',
  title: new TranslatableMarkup('OAuth2 (deprecated)')
)]
class OAuth2 extends AuthenticationPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    @trigger_error('The "oauth2" authentication plugin is deprecated in migrate_plus:6.0.11 and is removed from migrate_plus:7.0.0. Use the "oauth2_client" plugin instead. See https://www.drupal.org/project/migrate_plus/issues/2879665', E_USER_DEPRECATED);
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationOptions($url): array {
    $handlerStack = HandlerStack::create();
    $client = new Client([
      'handler' => $handlerStack,
      'base_uri' => $this->configuration['base_uri'],
      'auth' => 'oauth2',
    ]);

    $grant_type = match ($this->configuration['grant_type']) {
      'authorization_code' => new AuthorizationCode($client, $this->configuration),
      'client_credentials' => new ClientCredentials($client, $this->configuration),
      'urn:ietf:params:oauth:grant-type:jwt-bearer' => new JwtBearer($client, $this->configuration),
      'password' => new PasswordCredentials($client, $this->configuration),
      'refresh_token' => new RefreshToken($client, $this->configuration),
      default => throw new MigrateException("Unrecognized grant_type {$this->configuration['grant_type']}."),
    };
    $middleware = new OAuthMiddleware($client, $grant_type);

    return [
      'headers' => [
        'Authorization' => 'Bearer ' . $middleware->getAccessToken()->getToken(),
      ],
    ];
  }

}
