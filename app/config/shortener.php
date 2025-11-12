<?php

return [
    'hmac_key' => env('SHORTENER_HMAC_KEY'),
    'k_anon'   => (int) env('SHORTENER_K_ANON', 5),

    'allowed_schemes' => array_filter(array_map('trim', explode(',', env('SHORTENER_ALLOWED_SCHEMES', 'https')))),
    'domain_whitelist' => array_filter(array_map('trim', explode(',', env('SHORTENER_DOMAIN_WHITELIST', '')))),

    'rate_limits' => [
        'create_per_min'  => (int) env('SHORTENER_MAX_CREATE_PER_MINUTE', 30),
        'resolve_per_min' => (int) env('SHORTENER_MAX_RESOLVE_PER_MINUTE', 120),
    ],
];
