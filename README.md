# Traffical PHP SDK

[Traffical](https://traffical.io) is one control plane for experiments, feature flags, and adaptive
optimization. Instead of hard-coding decisions, you expose **typed parameters** — numbers, strings, booleans,
and JSON, not just on/off toggles — and control behavior across web, mobile, push, and backend from a single
place. Parameters are resolved **locally in the SDK** (sub-millisecond, no network round trips at runtime), and
metrics are computed **warehouse-native**, against data you own. Start with a feature flag, graduate it to an
A/B test, and let adaptive optimization shift traffic to the winning variant — all on the same parameter,
without a deploy.

This package is the **official PHP SDK** (PHP `^8.1`) that brings the Traffical parameter control plane to your
PHP services.

## Features

- **Local, in-process evaluation** — resolve a cached config bundle with no per-decision network call.
- **Typed parameters with safe defaults** — bool / string / number / JSON, each with a caller-provided fallback.
- **Layered experiments & targeting** — Google-style layered isolation, condition/attribute segmentation, and
  progressive (percentage) rollouts.
- **Adaptive optimization** — contextual-bandit scoring evaluated client-side.
- **BYO warehouse-native assignment logging** — route structured assignment rows through your own pipeline
  (Segment, RudderStack, a DB, a queue) so assignment data never has to leave your infrastructure.
- **Event tracking** — exposure, decision, and custom track events, batched and flushed PHP-FPM-aware via
  `fastcgi_finish_request()` so the response returns before events are sent.
- **Plugin system** — hook into the `decide` / `exposure` / `track` lifecycle from day one.
- **PSR-first & framework-ready** — PSR-18 HTTP, PSR-17 factories, PSR-16 cache, PSR-3 logger, and PSR-20 clock
  are all injectable; first-party Laravel, Symfony, and OpenFeature integrations.

## Modes

- **Bundle mode (default)** — the SDK fetches a config bundle, caches it (shared across PHP-FPM workers via a
  PSR-16 store), and resolves every parameter locally. No per-decision network call.
- **Server mode** — resolution is delegated to the Traffical edge via `POST /v1/resolve` (cached per request).
  Use it when you want zero client-side evaluation logic.

## Further reading

- [docs/warehouse-native.md](docs/warehouse-native.md) — BYO assignment logging and warehouse-native metrics
- [docs/plugins.md](docs/plugins.md) — the plugin lifecycle and built-in plugins
- [docs/php-lifecycle.md](docs/php-lifecycle.md) — event batching and flushing under PHP-FPM
- [docs/conformance.md](docs/conformance.md) — cross-language determinism and the bundle-vs-server tradeoffs
- [examples/](examples/) — runnable examples: basic, server mode, warehouse-native BYO, custom plugin, plus
  [Laravel](examples/laravel.md) and [Symfony](examples/symfony.md) guides

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
    apiKey: 'traffical_sk_…',
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

// Custom analytics events. Optional args (decisionId, value, values, unitKey,
// eventTimestamp) live in a TrackOptions bag:
$client->track('checkout_completed', ['orderId' => 'o-1001'], new Traffical\TrackOptions(
    decisionId: $decision->decisionId,
    value: 49.0,
));

// Flush is automatic on shutdown; call explicitly in worker/CLI contexts:
$client->flushEvents();

// In a long-lived process, close() runs teardown and awaits a final flush:
$client->close();
```

## Server mode

Delegate resolution to the edge worker instead of evaluating a local bundle. Each `getParams()`/`decide()`
performs (and caches per request) a `POST /v1/resolve`.

```php
$client = new Client(new ClientOptions(
    orgId: 'org_...',
    projectId: 'prj_...',
    env: 'production',
    apiKey: 'traffical_sk_…',
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
    apiKey: 'traffical_sk_…',
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
| `trackDecisions` | `true` | Emit a decision event per `decide()` |
| `batchSize` | `10` | Events per delivery batch (auto-flush threshold) |
| `flushIntervalMs` | `30000` | Event flush cadence (PHP flushes on batch-full or request end) |
| `deduplicateExposures` | `true` | Exposure session-dedup on/off (S4) |
| `exposureSessionTtlMs` | `1800000` | Exposure session-dedup TTL (30-minute session) |
| `configTimeoutMs` | `10000` | Config-fetch request timeout (see note below) |
| `eventsTimeoutMs` | `10000` | Event-delivery request timeout (see note below) |
| `resolveTimeoutMs` | `5000` | Server-resolve request timeout (see note below) |
| `configSource` | discovered | Custom `ConfigSource` (HTTP/file/inline/cached) |
| `eventTransport` | discovered | Custom `EventTransport` sink |
| `httpClient`, `requestFactory`, `streamFactory` | discovered | PSR-18/17 seams |
| `cache` | `null` | PSR-16 shared store (FPM workers share one bundle) |
| `logger` | `NullLogger` | PSR-3 logger |
| `clock` | system | PSR-20 clock |
| `plugins` | `[]` | Plugin list |

> **Request timeouts.** PSR-18 defines no portable timeout API, so the SDK
> cannot set connect/read timeouts on an arbitrary injected or auto-discovered
> HTTP client. The `configTimeoutMs` / `eventsTimeoutMs` / `resolveTimeoutMs`
> options carry the spec's intended values (10s / 10s / 5s); configure the
> matching timeout on the PSR-18 client you pass as `httpClient` (e.g. Guzzle's
> `connect_timeout` / `timeout`) to enforce them.

## Cross-language conformance

The PHP SDK shares the language-agnostic [Traffical SDK spec](tests/sdk-spec/test-vectors/README.md) with the
JS/TS and Swift SDKs: the same SHA-256 v2 (UTF-8 byte) bucketing, the same layered resolution engine, and the same
contextual-bandit scoring. Every release is gated on the spec's deterministic conformance vectors, so a given
unit buckets identically on every platform. The fixtures are pinned via the `tests/sdk-spec` git submodule and
run as part of `composer conformance`.

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
