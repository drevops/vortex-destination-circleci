<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\authentication;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate_plus\authentication\OAuth2Client;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the oauth2_client authentication plugin.
 */
#[CoversClass(OAuth2Client::class)]
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
final class OAuth2ClientTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate_plus', 'migrate'];

  /**
   * Creates a plugin instance with the given configuration.
   */
  private function createPlugin(array $configuration): OAuth2Client {
    return new OAuth2Client($configuration, 'oauth2_client', []);
  }

  /**
   * Tests that missing provider_options throws.
   */
  public function testMissingProviderOptionsThrows(): void {
    $plugin = $this->createPlugin(['grant_type' => 'client_credentials']);
    $this->expectException(MigrateException::class);
    $plugin->getAuthenticationOptions('http://example.com');
  }

  /**
   * Tests that empty provider_options throws.
   */
  public function testEmptyProviderOptionsThrows(): void {
    $plugin = $this->createPlugin(['grant_type' => 'client_credentials', 'provider_options' => []]);
    $this->expectException(MigrateException::class);
    $plugin->getAuthenticationOptions('http://example.com');
  }

  /**
   * Tests that missing grant_type throws.
   */
  public function testMissingGrantTypeThrows(): void {
    $plugin = $this->createPlugin(['provider_options' => ['clientId' => 'id']]);
    $this->expectException(MigrateException::class);
    $plugin->getAuthenticationOptions('http://example.com');
  }

  /**
   * Tests that an empty grant_type throws.
   */
  public function testEmptyGrantTypeThrows(): void {
    $plugin = $this->createPlugin(['provider_options' => ['clientId' => 'id'], 'grant_type' => '']);
    $this->expectException(MigrateException::class);
    $plugin->getAuthenticationOptions('http://example.com');
  }

  /**
   * Tests that a Bearer Authorization header is returned.
   */
  public function testReturnsAuthorizationHeader(): void {
    $access_token = $this->createStub(AccessToken::class);
    $access_token->method('getToken')->willReturn('test-token-value');

    $provider = $this->createStub(GenericProvider::class);
    $provider->method('getAccessToken')->willReturn($access_token);

    $plugin = new class(
      [
        'provider_options' => ['clientId' => 'id'],
        'grant_type' => 'client_credentials',
        'grant_options' => [],
      ],
      'oauth2_client',
      [],
    ) extends OAuth2Client {

      /**
       * Mocked provider for testing.
       */
      public GenericProvider $mockProvider;

      /**
       * {@inheritdoc}
       */
      public function getAuthenticationOptions($url): array {
        $grant_type = $this->configuration['grant_type'];
        $grant_options = $this->configuration['grant_options'] ?? [];
        $access_token = $this->mockProvider->getAccessToken($grant_type, $grant_options);
        return ['headers' => ['Authorization' => 'Bearer ' . $access_token->getToken()]];
      }

    };
    $plugin->mockProvider = $provider;

    $result = $plugin->getAuthenticationOptions('http://example.com');
    $this->assertSame(['headers' => ['Authorization' => 'Bearer test-token-value']], $result);
  }

}
