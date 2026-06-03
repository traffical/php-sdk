<?php

declare(strict_types=1);

/**
 * Bring-your-own (BYO) warehouse-native assignment logging.
 *
 * Routes structured assignment rows through your own pipeline instead of (or in
 * addition to) Traffical's managed event sync. Here we use WarehouseNativeLogger
 * to map each AssignmentLogEntry to a snake_case row and "insert" it.
 */

require __DIR__ . '/../vendor/autoload.php';

use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Warehouse\WarehouseNativeLogger;

// Your sink: HTTP API, CDP (Segment/RudderStack), queue, or direct DB insert.
$sink = function (array $row): void {
    // Includes stable keys for warehouse joins: policy_key, allocation_key,
    // plus decision_id, type ("decision"|"exposure"), assignment_id, and any
    // filtered-context properties spread to the top level.
    fwrite(STDOUT, json_encode($row, JSON_PRETTY_PRINT) . "\n");
};

$client = new Client(new ClientOptions(
    orgId: 'org_demo',
    projectId: 'prj_demo',
    env: 'production',
    apiKey: 'sdk_demo',
    assignmentLogger: new WarehouseNativeLogger($sink),
    // Keep assignment data entirely on your own infrastructure:
    disableCloudEvents: true,
));

$context = ['userId' => 'user-abc', 'country' => 'US'];

// decide() emits a "decision" row per matched layer (subject to dedup).
$decision = $client->decide($context, ['hero_variant' => 'control']);

// trackExposure() emits an "exposure" row per matched layer. Because `type` is
// part of the dedup key, both rows are distinct.
$client->trackExposure($decision);

$client->flushEvents();
