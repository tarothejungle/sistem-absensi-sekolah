<?php

return [
    'enabled' => env('PWA_ENABLED', true),
    'push_enabled' => env('PUSH_ENABLED', false),
    'vapid_subject' => env('PUSH_VAPID_SUBJECT', 'mailto:admin@example.com'),
    'vapid_public_key' => env('PUSH_VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('PUSH_VAPID_PRIVATE_KEY'),
    'push_ttl' => (int) env('PUSH_TTL', 300),
    'push_retry_after_seconds' => (int) env('PUSH_RETRY_AFTER_SECONDS', 300),
    'push_rate_limit_per_minute' => (int) env('PUSH_RATE_LIMIT_PER_MINUTE', 10),
    'allowed_push_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'PUSH_ALLOWED_HOSTS',
            'updates.push.services.mozilla.com,fcm.googleapis.com,*.push.apple.com,*.notify.windows.com'
        ))
    ))),
];
