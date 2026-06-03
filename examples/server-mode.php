<?php

declare(strict_types=1);

/**
 * Server evaluation mode: resolution is delegated to the Traffical edge worker
 * via POST /v1/resolve instead of evaluating a local bundle. The response is
 * cached for the duration of the request, so repeated getParams()/decide() calls
 * with the same context do not re-hit the network.
 */

require __DIR__ . '/../vendor/autoload.php';

use Traffical\Client;
use Traffical\ClientOptions;

$client = new Client(new ClientOptions(
    orgId: 'org_demo',
    projectId: 'prj_demo',
    env: 'production',
    apiKey: 'sdk_demo',
    evaluationMode: 'server',
));

$context = ['userId' => 'user-abc', 'country' => 'US'];

$params = $client->getParams($context, ['hero_variant' => 'control']);
printf("hero variant: %s\n", (string) $params['hero_variant']);

$decision = $client->decide($context, ['hero_variant' => 'control']);
$client->trackExposure($decision);

printf("config version: %s\n", (string) ($client->getConfigVersion() ?? 'n/a'));

$client->flushEvents();
