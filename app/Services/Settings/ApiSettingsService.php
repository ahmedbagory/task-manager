<?php

namespace App\Services\Settings;

use App\Models\ApiSetting;

class ApiSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function getWhatsAppSettings(): array
    {
        $setting = ApiSetting::query()->find(1);

        return [
            'provider' => $setting?->provider ?: (string) config('whatsapp.provider', 'meta'),
            'enforce_selected_provider' => $setting?->enforce_selected_provider ?? $this->configBool('whatsapp.enforce_selected_provider', false),
            'outbound_enabled' => $setting?->outbound_enabled ?? $this->configBool('whatsapp.outbound_enabled', false),
            'bridge_outbound_target' => $setting?->bridge_outbound_target ?: (string) config('whatsapp.bridge_outbound_target', 'direct_phone'),
            'verify_token' => $setting?->verify_token ?: (string) config('whatsapp.verify_token', ''),
            'access_token' => $setting?->access_token ?: (string) config('whatsapp.access_token', ''),
            'bridge_secret' => $setting?->bridge_secret ?: (string) config('whatsapp.inbound_bridge_secret', ''),
            'phone_number_id' => $setting?->phone_number_id ?: (string) config('whatsapp.phone_number_id', ''),
            'api_base_url' => $setting?->api_base_url ?: (string) config('whatsapp.api_base_url', 'https://graph.facebook.com'),
            'graph_version' => $setting?->graph_version ?: (string) config('whatsapp.graph_version', 'v23.0'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateWhatsAppSettings(array $data): ApiSetting
    {
        $setting = $this->getOrCreate();
        $current = $this->getWhatsAppSettings();

        $setting->fill([
            'provider' => (string) ($data['provider'] ?? $current['provider']),
            'enforce_selected_provider' => filter_var($data['enforce_selected_provider'] ?? $current['enforce_selected_provider'], FILTER_VALIDATE_BOOL),
            'outbound_enabled' => filter_var($data['outbound_enabled'] ?? $current['outbound_enabled'], FILTER_VALIDATE_BOOL),
            'bridge_outbound_target' => (string) ($data['bridge_outbound_target'] ?? $current['bridge_outbound_target']),
            'verify_token' => (string) ($data['verify_token'] ?? $current['verify_token']),
            'access_token' => (string) ($data['access_token'] ?? $current['access_token']),
            'bridge_secret' => (string) ($data['bridge_secret'] ?? $current['bridge_secret']),
            'phone_number_id' => (string) ($data['phone_number_id'] ?? $current['phone_number_id']),
            'api_base_url' => (string) ($data['api_base_url'] ?? $current['api_base_url']),
            'graph_version' => (string) ($data['graph_version'] ?? $current['graph_version']),
        ]);

        $setting->save();

        return $setting->refresh();
    }

    public function getWhatsAppValue(string $key, mixed $default = null): mixed
    {
        return $this->getWhatsAppSettings()[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public function getMessageTemplates(): array
    {
        $setting = ApiSetting::query()->find(1);
        $stored = is_array($setting?->message_templates) ? $setting->message_templates : [];

        return array_merge(self::defaultMessageTemplates(), $stored);
    }

    /**
     * @param  array<string, mixed>  $templates
     */
    public function updateMessageTemplates(array $templates): void
    {
        $setting = $this->getOrCreate();

        $existing = is_array($setting->message_templates) ? $setting->message_templates : [];

        foreach ($templates as $key => $value) {
            if (str_starts_with($key, 'enabled_')) {
                $existing[$key] = filter_var($value, FILTER_VALIDATE_BOOL);
            } elseif (is_string($value) && trim($value) !== '') {
                $existing[$key] = trim($value);
            } else {
                unset($existing[$key]);
            }
        }

        $setting->message_templates = $existing;
        $setting->save();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultMessageTemplates(): array
    {
        return [
            'task_registered' => 'تم تسجيل بلاغك رقم {task_number}',
            'task_assigned' => 'تم تحويل البلاغ رقم {task_number} إلى {assignee_name}',
            'task_completed' => 'تم إغلاق البلاغ رقم {task_number}',
            'enabled_task_registered' => true,
            'enabled_task_assigned' => true,
            'enabled_task_completed' => true,
        ];
    }

    private function getOrCreate(): ApiSetting
    {
        return ApiSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'provider' => (string) config('whatsapp.provider', 'meta'),
                'enforce_selected_provider' => $this->configBool('whatsapp.enforce_selected_provider', false),
                'outbound_enabled' => $this->configBool('whatsapp.outbound_enabled', false),
                'bridge_outbound_target' => (string) config('whatsapp.bridge_outbound_target', 'direct_phone'),
                'verify_token' => (string) config('whatsapp.verify_token', ''),
                'access_token' => (string) config('whatsapp.access_token', ''),
                'bridge_secret' => (string) config('whatsapp.inbound_bridge_secret', ''),
                'phone_number_id' => (string) config('whatsapp.phone_number_id', ''),
                'api_base_url' => (string) config('whatsapp.api_base_url', 'https://graph.facebook.com'),
                'graph_version' => (string) config('whatsapp.graph_version', 'v23.0'),
            ]
        );
    }

    private function configBool(string $key, bool $default): bool
    {
        return filter_var(config($key, $default), FILTER_VALIDATE_BOOL);
    }
}
