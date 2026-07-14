<?php

declare(strict_types=1);

/**
 * Basic bundle-mode usage: resolve parameters, make a decision, track exposure.
 *
 * Run with a real SDK key:
 *   TRAFFICAL_API_KEY=... php examples/basic.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Traffical\Client;
use Traffical\ClientOptions;

$client = new Client(new ClientOptions(
    orgId: getenv('TRAFFICAL_ORG_ID') ?: 'org_demo',
    projectId: getenv('TRAFFICAL_PROJECT_ID') ?: 'prj_demo',
    env: getenv('TRAFFICAL_ENV') ?: 'production',
    apiKey: getenv('TRAFFICAL_API_KEY') ?: 'sdk_demo',
));

$context = [
    'userId' => 'user-abc',
    'country' => 'US',
    'plan' => 'pro',
];

// 1) Resolve a bag of parameters with defaults.
$params = $client->getParams($context, [
    'checkout_button_color' => 'blue',
    'discount_pct' => 0,
]);

printf("button color: %s\n", (string) $params['checkout_button_color']);
printf("discount:     %s%%\n", (string) $params['discount_pct']);

// 2) Make a decision and record the exposure.
$decision = $client->decide($context, ['hero_variant' => 'control']);
printf("hero variant: %s (decision %s)\n", (string) $decision->assignments['hero_variant'], $decision->decisionId);

$client->trackExposure($decision);

// 3) Track a downstream conversion, attributed to the decision. Optional
//    arguments (decisionId, value, values, unitKey, eventTimestamp) live in a
//    TrackOptions bag.
$client->track('checkout_completed', ['orderId' => 'o-1001'], new Traffical\TrackOptions(
    decisionId: $decision->decisionId,
    value: 49.0,
));

// Events flush automatically on shutdown; flush explicitly in CLI/worker code.
$client->flushEvents();

// In a long-lived process, close() runs teardown and awaits a final flush.
$client->close();
