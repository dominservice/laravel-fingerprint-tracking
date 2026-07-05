<?php

return [
    'enabled' => function_exists('optimize_config')
        ? optimize_config('FINGERPRINT_TRACKING_ENABLED', true)
        : env('FINGERPRINT_TRACKING_ENABLED', true),

    'cookie' => [
        'name' => function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_COOKIE', 'fingerprint')
            : env('FINGERPRINT_TRACKING_COOKIE', 'fingerprint'),
        'minutes' => (int) (function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_COOKIE_MINUTES', 525600)
            : env('FINGERPRINT_TRACKING_COOKIE_MINUTES', 525600)),
        'same_site' => function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_COOKIE_SAME_SITE', 'lax')
            : env('FINGERPRINT_TRACKING_COOKIE_SAME_SITE', 'lax'),
        'secure' => function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_COOKIE_SECURE', false)
            : env('FINGERPRINT_TRACKING_COOKIE_SECURE', false),
    ],

    'routes' => [
        'prefix' => function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_PREFIX', 'fingerprint-tracking')
            : env('FINGERPRINT_TRACKING_PREFIX', 'fingerprint-tracking'),
        'middleware' => ['web'],
    ],

    'auth' => [
        'bind_authenticated_user' => true,
        'morph_name' => 'auth_subject',
        'key_type' => function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_AUTH_KEY_TYPE', 'id')
            : env('FINGERPRINT_TRACKING_AUTH_KEY_TYPE', 'id'),
    ],

    'tracking' => [
        'auto_page_view' => true,
        'attach_hidden_fields' => true,
        'form_selector' => 'form[data-invis], form[data-fingerprint-track]',
        'event_name' => 'page_view',
        'request_attribute' => 'fingerprint_tracking_event',
        'payload_attribute' => 'fingerprint_tracking_payload',
    ],

    'geo' => [
        'enabled' => function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_GEO_ENABLED', false)
            : env('FINGERPRINT_TRACKING_GEO_ENABLED', false),
        'driver' => function_exists('optimize_config')
            ? optimize_config('FINGERPRINT_TRACKING_GEO_DRIVER', 'ipregistry')
            : env('FINGERPRINT_TRACKING_GEO_DRIVER', 'ipregistry'),
        'ipregistry' => [
            'key' => function_exists('optimize_config')
                ? optimize_config('IPREGISTRY_KEY')
                : env('IPREGISTRY_KEY'),
            'timeout' => (int) (function_exists('optimize_config')
                ? optimize_config('FINGERPRINT_TRACKING_IPREGISTRY_TIMEOUT', 3)
                : env('FINGERPRINT_TRACKING_IPREGISTRY_TIMEOUT', 3)),
        ],
    ],
];
