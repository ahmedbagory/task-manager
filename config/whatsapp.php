<?php

use App\Support\PublicUrl;

$publicAppUrl = PublicUrl::resolveAppUrl(
    appUrl: env('APP_URL'),
    publicAppUrl: env('PUBLIC_APP_URL'),
    laravelAppUrl: env('LARAVEL_APP_URL'),
    environment: env('APP_ENV', 'production'),
);

return [
    'provider' => env('WHATSAPP_PROVIDER', 'meta'),
    'enforce_selected_provider' => env('WHATSAPP_ENFORCE_SELECTED_PROVIDER', false),
    'outbound_enabled' => env('WHATSAPP_OUTBOUND_ENABLED', false),
    'bridge_outbound_target' => env('WHATSAPP_BRIDGE_OUTBOUND_TARGET', 'direct_phone'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'inbound_bridge_secret' => env('INBOUND_BRIDGE_SECRET'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'api_base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
    'bridge_api_port' => env('WHATSAPP_BRIDGE_API_PORT', 3001),
    'public_app_url' => $publicAppUrl,
    'public_api_url' => PublicUrl::resolveApiUrl(
        apiUrl: env('LARAVEL_API_URL', env('API_BASE_URL')),
        appUrl: env('APP_URL'),
        publicAppUrl: env('PUBLIC_APP_URL'),
        laravelAppUrl: env('LARAVEL_APP_URL'),
        environment: env('APP_ENV', 'production'),
    ),
    'webhook_urls' => [
        'manual' => env('WHATSAPP_MANUAL_WEBHOOK_URL', $publicAppUrl.'/webhooks/inbound-message'),
        'meta_verify' => env('WHATSAPP_META_VERIFY_URL', $publicAppUrl.'/webhooks/meta/whatsapp'),
        'meta_receive' => env('WHATSAPP_META_RECEIVE_URL', $publicAppUrl.'/webhooks/meta/whatsapp'),
        'twilio' => env('WHATSAPP_TWILIO_WEBHOOK_URL', $publicAppUrl.'/webhooks/twilio/whatsapp'),
        'dialog360' => env('WHATSAPP_360DIALOG_WEBHOOK_URL', $publicAppUrl.'/webhooks/360dialog/whatsapp'),
        'bridge_webhook' => env('WHATSAPP_BRIDGE_WEBHOOK_URL', $publicAppUrl.'/webhooks/inbound-message'),
        'bridge_heartbeat' => env('WHATSAPP_BRIDGE_HEARTBEAT_URL', $publicAppUrl.'/webhooks/bridge/heartbeat'),
        'bridge_outbound_pull' => env('WHATSAPP_BRIDGE_OUTBOUND_PULL_URL', $publicAppUrl.'/webhooks/bridge/outbound'),
    ],
];
