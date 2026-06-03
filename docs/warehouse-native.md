# Warehouse-Native Experimentation (PHP)

Traffical supports **warehouse-native metrics** — compute experiment results directly from assignments and
facts that live in your data warehouse. The SDK's job is to log which units were assigned to which variants.

There are two ways to get assignment data into your warehouse:

1. **Traffical-managed sync** — the SDK sends events to Traffical; Traffical syncs them into your warehouse.
2. **Bring-your-own (BYO) assignment logging** — you route structured assignment rows through your own
   pipeline (an HTTP API, a CDP, a queue, a direct insert) without Traffical ever storing them.

This is the PHP port of the [JS warehouse-native guide](https://docs.traffical.io); the options map 1:1 onto
`ClientOptions`.

---

## Choosing an integration mode

| Mode | How assignments reach the warehouse | SDK setup |
|------|-------------------------------------|-----------|
| **Managed sync** | Traffical receives events → syncs to your warehouse | Default — no extra config |
| **BYO `assignmentLogger`** | Your pipeline (HTTP, CDP, DB, queue) | Pass `assignmentLogger`; optionally set `disableCloudEvents: true` |

Behavioral data (purchases, conversions) typically already lives in your warehouse as **fact tables**.
Assignments are the piece the SDK provides.

---

## BYO assignment logging

Pass an `assignmentLogger` — either a plain callable `(AssignmentLogEntry): void` or an
`AssignmentLogger` implementation:

```php
use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Types\AssignmentLogEntry;

$client = new Client(new ClientOptions(
    orgId: 'org_...',
    projectId: 'prj_...',
    env: 'production',
    apiKey: '...',
    assignmentLogger: function (AssignmentLogEntry $entry): void {
        // Forward to your HTTP API / CDP / queue.
    },
));
```

### `WarehouseNativeLogger` helper

`WarehouseNativeLogger` maps each entry to a `snake_case` row and forwards it to your sink. Unlike the early
JS plugin, it **includes** `policy_key` and `allocation_key` so warehouse joins can use the stable keys.
Filtered-context `properties` are spread onto the row as covariates (useful for CUPED).

```php
use Traffical\Warehouse\WarehouseNativeLogger;

$logger = new WarehouseNativeLogger(function (array $row): void {
    // $row keys: unit_key, policy_id, policy_key, allocation_name, allocation_key,
    // timestamp, layer_id, allocation_id, org_id, project_id, env, type,
    // decision_id, anonymous_id, assignment_id, + any context properties.
    $db->insert('experiment_assignments', $row);
});

$client = new Client(new ClientOptions(/* ... */, assignmentLogger: $logger));
```

### `AssignmentLogEntry` fields

| Field | Description |
|-------|-------------|
| `unitKey` | The unit identifier used for bucketing |
| `policyId` | Experiment (policy) identifier |
| `policyKey` | Stable experiment key — use for warehouse joins |
| `allocationName` | The assigned variant |
| `allocationKey` | Stable variant key — use for warehouse joins |
| `allocationId` | Allocation identifier |
| `timestamp` | ISO 8601 assignment time |
| `layerId` | Layer identifier |
| `orgId`, `projectId`, `env` | Scoping fields |
| `type` | `AssignmentType::Decision` or `AssignmentType::Exposure` |
| `decisionId` | The decision that produced this assignment |
| `anonymousId` | Anonymous/device id (client SDKs); always `null` on the server |
| `id` | Unique id for this assignment row (`asn_…`) |
| `properties` | Filtered evaluation context — useful as covariates |

---

## When does `assignmentLogger` fire?

- **`decide()`** — after resolving parameters, the logger fires once per layer with a matched experiment and
  variant, with `type: decision` (subject to dedup).
- **`trackExposure()`** — fires once per matched layer with `type: exposure`.

Because `type` is part of the dedup key, calling both `decide()` and `trackExposure()` for the same decision
produces two distinct rows (one `decision`, one `exposure`); repeated calls of the same kind are
deduplicated. `attributionOnly` layers still emit. The logger is **not** called when `unitKey` is missing.

`disableCloudEvents` does **not** gate the assignment logger — it only stops events from being sent to
Traffical.

---

## Deduplication

`deduplicateAssignmentLogger` (default `true`) deduplicates logger calls by
`unitKey:policyId:allocationName:type`.

- **Per-request (default):** an in-memory LRU. Under PHP-FPM each request starts fresh.
- **Cross-request:** supply a PSR-16 `cache` to back deduplication across requests/workers
  (`CachedAssignmentLoggerDedup`).

Set `deduplicateAssignmentLogger: false` for a row on every `decide()`/`trackExposure()` (audit logging).

Assignment-logger dedup and cloud-exposure dedup are **independent**: cloud exposure events are deduplicated
by the built-in `DecisionDeduplicator` (active when `disableCloudEvents` is `false`).

---

## Disabling cloud events

```php
new ClientOptions(/* ... */, disableCloudEvents: true, assignmentLogger: $logger);
```

When `true`, the SDK stops sending decision/exposure/track events to Traffical. Config fetching and
`decide()` continue to work. Use it for compliance (assignment data never leaves your infra) or to avoid
double-counting while running both managed and BYO paths. Always pair with a BYO logger or another path.

---

## Plugin route

If you prefer the plugin model, `WarehouseNativeLoggerPlugin` listens on `onExposure` and forwards entries to
an `AssignmentLogger`. Use either the `assignmentLogger` option **or** the plugin — not both — to avoid double
counting.

```php
use Traffical\Plugins\WarehouseNativeLoggerPlugin;
use Traffical\Warehouse\WarehouseNativeLogger;

$options = new ClientOptions(/* ... */, plugins: [
    new WarehouseNativeLoggerPlugin(new WarehouseNativeLogger($sink)),
]);
```
