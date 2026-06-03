<?php

declare(strict_types=1);

/**
 * Traffical SDK configuration. Publish with:
 *   php artisan vendor:publish --tag=traffical-config
 */
return [
    'org_id' => env('TRAFFICAL_ORG_ID', ''),
    'project_id' => env('TRAFFICAL_PROJECT_ID', ''),
    'env' => env('TRAFFICAL_ENV', env('APP_ENV', 'production')),
    'api_key' => env('TRAFFICAL_API_KEY', ''),
    'base_url' => env('TRAFFICAL_BASE_URL', 'https://sdk.traffical.io'),

    // "bundle" (local evaluation from a cached config) or "server" (POST /v1/resolve).
    'evaluation_mode' => env('TRAFFICAL_EVALUATION_MODE', 'bundle'),

    'disable_cloud_events' => (bool) env('TRAFFICAL_DISABLE_CLOUD_EVENTS', false),
    'deduplicate_assignment_logger' => (bool) env('TRAFFICAL_DEDUPLICATE_ASSIGNMENT_LOGGER', true),
];
