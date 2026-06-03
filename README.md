# Traffical PHP SDK

Deterministic feature flags, experiments, and **warehouse-native experimentation** for PHP `^8.1`.

The PHP SDK shares the language-agnostic [Traffical SDK spec](tests/sdk-spec/test-vectors/README.md) with the
JS/TS and Swift SDKs: the same FNV-1a (UTF-8 byte) bucketing, the same layered resolution engine, the same
contextual-bandit scoring. Every release is gated on the spec's deterministic conformance vectors, so a unit
buckets identically on every platform.

- Local **bundle mode** — resolve parameters in-process from a cached config (no per-decision network call).
- **Server mode** — delegate resolution to the Traffical edge via `POST /v1/resolve`.
- **BYO warehouse-native assignment logging** — route structured assignment rows through your own pipeline.
- PSR-first: PSR-18 HTTP client, PSR-17 factories, PSR-16 cache, PSR-3 logger, PSR-20 clock are all injectable.
- PHP-FPM aware: events flush on shutdown after the response is returned via `fastcgi_finish_request()`.

## Installation

```bash
composer require traffical/sdk
```

You also need a PSR-18 HTTP client and PSR-17 factories. Any compliant implementation works; the SDK
auto-discovers them via [php-http/discovery](https://docs.php-http.org/en/latest/discovery.html):

```bash
composer require guzzlehttp/guzzle nyholm/psr7
# or: composer require symfony/http-client nyholm/psr7
```

## Quickstart (bundle mode)

```php
use Traffical\Client;
use Traffical\ClientOptions;

$client = new Client(new ClientOptions(
    orgId: 'org_...',
    projectId: 'prj_...',
    env: 'production',
    apiKey: 'your_sdk_key',
));

// Resolve parameters with defaults as the fallback.
$params = $client->getParams(
    context: ['userId' => 'user-abc', 'country' => 'US'],
    defaults: ['checkout_button_color' => 'blue', 'discount_pct' => 0],
);

$color = $params['checkout_button_color'];
```

### Decide + track exposure

`decide()` returns a `DecisionResult` (assignments + metadata). Call `trackExposure()` when the user actually
sees the treatment so exposures are attributed correctly.

```php
$decision = $client->decide(
    context: ['userId' => 'user-abc'],
    defaults: ['hero_variant' => 'control'],
);

$variant = $decision->assignments['hero_variant'];

// ...render the variant, then record that the user saw it:
$client->trackExposure($decision);

// Custom analytics events:
$client->track('checkout_completed', ['revenue' => 49.0], $decision->decisionId);

// Flush is automatic on shutdown; call explicitly in worker/CLI contexts:
$client->flushEvents();
```

## Server mode

Delegate resolution to the edge worker instead of evaluating a local bundle. Each `getParams()`/`decide()`
performs (and caches per request) a `POST /v1/resolve`.

```php
$client = new Client(new ClientOptions(
    orgId: 'org_...',
    projectId: 'prj_...',
    env: 'production',
    apiKey: 'your_sdk_key',
    evaluationMode: 'server',
));
```

See [docs/conformance.md](docs/conformance.md) for the bundle-vs-server tradeoffs.

## BYO warehouse-native assignment logging

Pass an `assignmentLogger` to route structured rows through your own pipeline. The `WarehouseNativeLogger`
helper maps each entry to a `snake_case` row (including the stable `policy_key`/`allocation_key` used for
warehouse joins):

```php
use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Warehouse\WarehouseNativeLogger;

$logger = new WarehouseNativeLogger(function (array $row): void {
    // INSERT $row into your warehouse / CDP / queue.
});

$client = new Client(new ClientOptions(
    orgId: 'org_...',
    projectId: 'prj_...',
    env: 'production',
    apiKey: 'your_sdk_key',
    assignmentLogger: $logger,
    disableCloudEvents: true, // keep assignment data on your own infra
));
```

Full guide: [docs/warehouse-native.md](docs/warehouse-native.md).

## Plugins

Hook into the SDK lifecycle (`onBeforeDecision`, `onDecision`, `onExposure`, `onTrack`, …). Built-ins:
`DebugPlugin`, `DecisionTrackingPlugin`, `WarehouseNativeLoggerPlugin`.

```php
use Traffical\ClientOptions;
use Traffical\Plugins\DebugPlugin;

$options = new ClientOptions(/* ... */, plugins: [new DebugPlugin()]);
```

Full guide: [docs/plugins.md](docs/plugins.md).

## Framework integrations

- **Laravel** — auto-discovered `TrafficalServiceProvider` + `Traffical` facade. See [examples/laravel.md](examples/laravel.md).
- **Symfony** — `TrafficalBundle` with a `traffical` config tree. See [examples/symfony.md](examples/symfony.md).
- **OpenFeature** — optional `TrafficalProvider` (requires `open-feature/sdk`).

## PHP lifecycle

The client registers a shutdown handler that calls `fastcgi_finish_request()` (when available) so the HTTP
response is returned to the user *before* events are flushed. See [docs/php-lifecycle.md](docs/php-lifecycle.md).

## Configuration reference

`ClientOptions` is an immutable value object. Construct it with named arguments, or refine an existing instance
with the fluent `with*()` methods (each returns a new instance):

| Option | Default | Description |
|--------|---------|-------------|
| `orgId`, `projectId`, `env`, `apiKey` | — | Required scoping + auth |
| `baseUrl` | `https://sdk.traffical.io` | Control-plane base URL |
| `localConfig` | `null` | Bootstrap/offline `ConfigBundle` |
| `refreshIntervalMs` | `60000` | Cached bundle TTL |
| `evaluationMode` | `bundle` | `bundle` (local) or `server` |
| `assignmentLogger` | `null` | BYO warehouse logger (callable or `AssignmentLogger`) |
| `disableCloudEvents` | `false` | Stop sending events to Traffical |
| `deduplicateAssignmentLogger` | `true` | Dedup logger calls per request |
| `eventBatchSize` | `10` | Auto-flush threshold |
| `httpClient`, `requestFactory`, `streamFactory` | discovered | PSR-18/17 seams |
| `cache` | `null` | PSR-16 shared store (FPM workers share one bundle) |
| `logger` | `NullLogger` | PSR-3 logger |
| `clock` | system | PSR-20 clock |
| `plugins` | `[]` | Plugin list |

## Development

```bash
composer install
composer test         # full PHPUnit suite (unit + conformance + integration)
composer conformance  # only the sdk-spec vectors
composer phpstan       # static analysis at level max
composer cs-check      # PSR-12 style check
```

Conformance fixtures are pinned via the `tests/sdk-spec` git submodule. After cloning:

```bash
git submodule update --init --recursive
```

## License

MIT
