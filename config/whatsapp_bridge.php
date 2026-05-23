<?php

return [
    'home_dir' => env('HOME', $_SERVER['HOME'] ?? '/home/' . get_current_user()),

    'pm2_name' => env('WHATSAPP_BRIDGE_PM2_NAME', 'whatsapp-bridge'),

    'bridge_path' => env('WHATSAPP_BRIDGE_PATH', base_path('whatsapp-bridge')),

    'pm2_bin' => env('WHATSAPP_BRIDGE_PM2_BIN', env('HOME', $_SERVER['HOME'] ?? '/home/' . get_current_user()) . '/.npm-global/bin/pm2'),

    'node_bin' => env('WHATSAPP_BRIDGE_NODE_BIN', '/opt/alt/alt-nodejs20/root/usr/bin/node'),

    'path_env' => env(
        'WHATSAPP_BRIDGE_PATH_ENV',
        env('HOME', $_SERVER['HOME'] ?? '/home/' . get_current_user()) . '/.npm-global/bin'
        . ':/opt/alt/alt-nodejs20/root/usr/bin'
        . ':/usr/local/bin:/usr/bin:/bin'
    ),

    'restart_cooldown_seconds' => (int) env('WHATSAPP_BRIDGE_RESTART_COOLDOWN', 30),

    'status_timeout' => 10,

    'restart_timeout' => 30,

    'log_lines' => 80,
];
