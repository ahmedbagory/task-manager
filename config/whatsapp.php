<?php

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
];
