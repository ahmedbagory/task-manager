<?php

namespace Tests\Feature\Services;

use App\Services\Settings\ApiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_whatsapp_settings_uses_config_defaults_when_database_record_missing(): void
    {
        config([
            'whatsapp.provider' => 'meta',
            'whatsapp.enforce_selected_provider' => true,
            'whatsapp.outbound_enabled' => true,
            'whatsapp.verify_token' => 'verify-from-config',
            'whatsapp.access_token' => 'access-from-config',
            'whatsapp.inbound_bridge_secret' => 'bridge-from-config',
            'whatsapp.phone_number_id' => 'phone-id-config',
            'whatsapp.api_base_url' => 'https://graph.facebook.com',
            'whatsapp.graph_version' => 'v23.0',
        ]);

        $settings = app(ApiSettingsService::class)->getWhatsAppSettings();

        $this->assertSame('meta', $settings['provider']);
        $this->assertTrue($settings['enforce_selected_provider']);
        $this->assertTrue($settings['outbound_enabled']);
        $this->assertSame('verify-from-config', $settings['verify_token']);
        $this->assertSame('access-from-config', $settings['access_token']);
        $this->assertSame('bridge-from-config', $settings['bridge_secret']);
        $this->assertSame('phone-id-config', $settings['phone_number_id']);
        $this->assertSame('https://graph.facebook.com', $settings['api_base_url']);
        $this->assertSame('v23.0', $settings['graph_version']);
    }

    public function test_update_whatsapp_settings_persists_values_and_encrypts_sensitive_tokens(): void
    {
        $service = app(ApiSettingsService::class);

        $service->updateWhatsAppSettings([
            'provider' => 'meta',
            'enforce_selected_provider' => true,
            'outbound_enabled' => true,
            'verify_token' => 'verify-token-secret',
            'access_token' => 'access-token-secret',
            'bridge_secret' => 'bridge-secret',
            'phone_number_id' => '1234567890',
            'api_base_url' => 'https://graph.facebook.com',
            'graph_version' => 'v23.0',
        ]);

        $settings = $service->getWhatsAppSettings();

        $this->assertTrue($settings['enforce_selected_provider']);
        $this->assertTrue($settings['outbound_enabled']);
        $this->assertSame('verify-token-secret', $settings['verify_token']);
        $this->assertSame('access-token-secret', $settings['access_token']);
        $this->assertSame('bridge-secret', $settings['bridge_secret']);
        $this->assertSame('1234567890', $settings['phone_number_id']);

        $storedVerifyToken = (string) DB::table('api_settings')->value('verify_token');
        $storedAccessToken = (string) DB::table('api_settings')->value('access_token');
        $storedBridgeSecret = (string) DB::table('api_settings')->value('bridge_secret');

        $this->assertNotSame('verify-token-secret', $storedVerifyToken);
        $this->assertNotSame('access-token-secret', $storedAccessToken);
        $this->assertNotSame('bridge-secret', $storedBridgeSecret);
    }
}
