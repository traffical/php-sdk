# Plugins

Plugins extend the SDK at well-defined lifecycle points without forking the client. They are registered via
`ClientOptions::$plugins` (or `withPlugins()`), sorted by descending priority, and run defensively: a throwing
hook is caught and logged via PSR-3, never breaking evaluation.

## The `Plugin` interface

Implement `Plugin` directly, or extend `AbstractPlugin` (no-op base) and override only what you need.

| Hook | Signature | Notes |
|------|-----------|-------|
| `name()` | `(): string` | Unique identifier; re-registering the same name replaces it |
| `priority()` | `(): int` | Higher runs earlier (default `0`) |
| `onInitialize` | `(PluginHost $client): void` | Capture the host for later callbacks |
| `onConfigUpdate` | `(ConfigBundle $bundle): void` | New config loaded |
| `onBeforeDecision` | `(array $context): array` | **Mutates** and returns the context |
| `onDecision` | `(DecisionResult $decision): void` | After `decide()` |
| `onResolve` | `(array $params): void` | After `getParams()` |
| `onExposure` | `(ExposureEvent $event): bool` | Return `false` to **cancel** the exposure |
| `onTrack` | `(TrackEvent $event): bool` | Return `false` to **cancel** the track event |
| `onDestroy` | `(): void` | Client teardown |

`PluginHost` is the minimal client surface exposed to plugins (`decide`, `getParams`, `trackExposure`,
`track`) — it avoids a circular dependency on the full `Client`.

## Writing a plugin

```php
use Traffical\Plugins\AbstractPlugin;
use Traffical\Types\DecisionResult;

final class RegionEnricher extends AbstractPlugin
{
    public function name(): string
    {
        return 'region-enricher';
    }

    public function priority(): int
    {
        return 500; // runs before lower-priority plugins
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function onBeforeDecision(array $context): array
    {
        $context['region'] = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'unknown';

        return $context;
    }

    public function onDecision(DecisionResult $decision): void
    {
        // e.g. mirror to your own telemetry.
    }
}
```

Register it:

```php
$options = new ClientOptions(/* ... */, plugins: [new RegionEnricher()]);
```

## Built-in plugins

### `DebugPlugin`

Logs every lifecycle hook via PSR-3 and keeps a bounded in-memory history for inspection in tests.

```php
use Traffical\Plugins\DebugPlugin;

$debug = new DebugPlugin($psrLogger);
$options = new ClientOptions(/* ... */, plugins: [$debug]);
// ...later, in a test:
$events = $debug->getEvents(); // list of ['hook' => ..., 'data' => ...]
```

### `DecisionTrackingPlugin`

Automatically calls `trackExposure()` on every `decide()` (auto-exposure). Use when `decide()` implies the
user experienced the allocation.

```php
use Traffical\Plugins\DecisionTrackingPlugin;

$options = new ClientOptions(/* ... */, plugins: [new DecisionTrackingPlugin()]);
```

### `WarehouseNativeLoggerPlugin`

Forwards exposure assignments to an `AssignmentLogger`. See [warehouse-native.md](warehouse-native.md).

## Cancelable hooks

`onExposure` and `onTrack` are cancelable: if **any** plugin returns `false`, the event is dropped (it is not
emitted to the transport). This is useful for sampling, consent gating, or PII filtering.
