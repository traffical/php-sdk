# Changelog

All notable changes to the Traffical PHP SDK are documented here. This project
adheres to [Semantic Versioning](https://semver.org/). Pre-1.0, breaking changes
land in minor releases.

## [0.2.0] - 2026-07-14

Drift-remediation release aligning the PHP SDK with the 0.7.0 Traffical SDK
spec + design contract. **Breaking** — this is a pre-1.0 minor with intentional
API and behavior changes (PHP breaks directly; only JS keeps deprecated
aliases).

### Breaking

- **Teardown:** `Client::destroy()` is replaced by the single verb
  `Client::close()`, which awaits a final event flush.
- **`track()` options bag:** `track(string $event, ?array $properties, ?TrackOptions $options)`
  replaces the positional `$decisionId` / `$unitKey` trailing args.
  `TrackOptions` carries `decisionId`, `unitKey`, `value`, `values`, and
  `eventTimestamp`.
- **Option renames:** `eventBatchSize` → `batchSize`. New canonical `*Ms`
  options: `flushIntervalMs`, `configTimeoutMs`, `eventsTimeoutMs`,
  `resolveTimeoutMs`, plus `deduplicateExposures` / `exposureSessionTtlMs`.

### Behavior (cross-SDK conformance)

- **S1** — an empty/whitespace-only layer `unitKey` override is rejected at parse
  and skips the layer (`bucket -1`, defaults, no exposure, no `unitKey`/
  `unitKeyValue`); never falls back to the project unit key.
- **S2** — numeric unit-key / entity / categorical values are stringified with a
  single canonical ECMAScript `Number::toString` rule so every SDK computes the
  same bucket (replaces the precision-14 cast).
- **S3 / S5** — condition relational operators require a numeric context value
  *and* a numeric threshold (no numeric-string coercion); an omitted threshold
  never matches. Array `.length` is supported in dot-notation lookups.
- **S4** — `trackExposure()` emits exactly one exposure event carrying only
  newly-exposed, non-`attributionOnly` layers with narrowed assignments; session
  dedup is on by default (bounded LRU + TTL).
- **S7** — contextual `modelVersion` sources `generatedAt ?? modelVersion`; the
  `policy.stateVersion` fallback is dropped (omit rather than emit a wrong
  label).
- **S8** — malformed bundles (missing `hashing`, empty `unitKey`, `bucketCount
  < 1`) are rejected at parse and the SDK fails open to the last-good bundle /
  `localConfig` / caller defaults; `decide()` / `getParams()` never crash.

### Events

- Empty `assignments` / `context` / `properties` / `values` serialize as `{}`
  (object), not `[]`.
- `latencyMs` serializes as an integer.
- `id` / `sdkName` / `sdkVersion` are omitted when null.
- Requires a 64-bit PHP build (runtime assertion in the assignment hash).

### Tooling

- Conformance wired against the full 0.7.0 vector set, plus event-payload
  validation against `events.schema.json` (`events_conformance.json`,
  `exposure_shape.json`).
- Added a stale-pin CI gate that fails when the pinned spec is behind the latest
  spec tag.

## [0.1.0]

- Initial PHP SDK: pure-PHP resolution engine, bundle + server evaluation modes,
  BYO warehouse-native assignment logging, plugin system, PSR-18/17/16/3/20
  seams, and conformance against the SDK spec vectors.
