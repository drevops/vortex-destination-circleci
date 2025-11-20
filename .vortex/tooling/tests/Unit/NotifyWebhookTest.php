<?php

declare(strict_types=1);

namespace DrevOps\VortexTooling\Tests\Unit;

require_once __DIR__ . '/../../src/helpers.php';

/**
 * Tests for notify-webhook script execution.
 */
class NotifyWebhookTest extends UnitTestCase {

  public function testNotifyWebhookSuccess(): void {
    self::envSetMultiple([
      'VORTEX_NOTIFY_WEBHOOK_PROJECT' => 'test-project',
      'VORTEX_NOTIFY_WEBHOOK_LABEL' => 'test-label',
      'VORTEX_NOTIFY_WEBHOOK_ENVIRONMENT_URL' => 'https://example.com',
      'VORTEX_NOTIFY_WEBHOOK_LOGIN_URL' => 'https://example.com/login',
      'VORTEX_NOTIFY_WEBHOOK_URL' => 'https://webhook.example.com/notify',
      'VORTEX_NOTIFY_WEBHOOK_METHOD' => 'POST',
      'VORTEX_NOTIFY_WEBHOOK_HEADERS' => 'Content-type: application/json',
      'VORTEX_NOTIFY_WEBHOOK_RESPONSE_STATUS' => '200',
      'VORTEX_NOTIFY_WEBHOOK_EVENT' => 'post_deployment',
    ]);

    $this->mockCurl([
      'status' => 200,
      'body' => '{"success":true}',
    ]);

    $output = $this->runScript('notify-webhook');

    // Verify output contains expected messages.
    $this->assertStringContainsString('[INFO] Started Webhook notification.', $output);
    $this->assertStringContainsString('[ OK ] Finished Webhook notification.', $output);
    $this->assertStringContainsString('Project            : test-project', $output);
    $this->assertStringContainsString('Deployment         : test-label', $output);
  }

}
