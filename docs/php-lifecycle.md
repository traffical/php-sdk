# PHP lifecycle & event flushing

PHP's request/response model differs from a long-lived Node process. The SDK is built for **PHP-FPM**: it
buffers analytics events in memory during the request and flushes them **after** the response is returned to
the client, so experimentation never adds latency to the user-visible response.

## How flushing works

On construction, the `Client` registers a shutdown handler:

1. At end of request, PHP runs registered shutdown functions.
2. The handler calls `fastcgi_finish_request()` **if available** — this returns the buffered HTTP response to
   the client immediately and lets PHP keep executing.
3. The batching transport then `flush()`es queued decision/exposure/track events over PSR-18 HTTP.
4. `flush()` is **fire-and-forget**: transport errors are caught and logged via PSR-3; they never surface to
   the user or throw during shutdown.

This deliberately avoids two patterns seen in other SDKs:

- No `shell_exec()` to spawn a background curl — no process-spawn surface.
- No blocking work in `__destruct()` that would delay the response.

## Auto-flush threshold

Events also flush automatically once the in-memory queue reaches `eventBatchSize` (default `10`), bounding
memory for long-running requests.

## CLI, queue workers, and long-running processes

`fastcgi_finish_request()` does not exist under the CLI SAPI or in long-lived workers. In those contexts:

- Call `$client->flushEvents()` explicitly at safe checkpoints (e.g. after each job).
- Or call `$client->destroy()` at shutdown, which runs plugin `onDestroy` hooks and flushes.

```php
// Queue worker loop
foreach ($jobs as $job) {
    $decision = $client->decide($job->context, $defaults);
    // ...handle job...
    $client->trackExposure($decision);
    $client->flushEvents(); // don't accumulate across the whole worker lifetime
}
```

## Sharing config across FPM workers

Each FPM worker is a separate process. To avoid every worker refetching the config bundle on its first
request, inject a **PSR-16 shared cache** (e.g. `symfony/cache` with APCu/Redis):

```php
use Traffical\ClientOptions;

$options = new ClientOptions(/* ... */, cache: $psr16Cache);
```

The `CachedConfigSource` keys the bundle by `projectId:env` and refreshes lazily on a TTL
(`refreshIntervalMs`, default 60s), so workers share one bundle rather than fetching per request. The same
shared cache can back cross-request assignment-logger deduplication.

## Disabling events entirely

Set `disableCloudEvents: true` to skip the transport altogether (e.g. when using BYO warehouse logging). The
shutdown handler is still registered but has nothing to send.
