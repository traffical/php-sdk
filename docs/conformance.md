# Conformance

The PHP SDK is gated on the language-agnostic **Traffical SDK spec**. The spec defines deterministic test
vectors — `bundle_*.json` inputs paired with `expected_*.json` outputs — that every SDK (PHP, JS/TS, Swift)
must reproduce exactly. This guarantees a unit buckets into the same variant on every platform.

## How it's wired

- The spec is pinned as a git submodule at `tests/sdk-spec`.
- `tests/Conformance/SpecVectorsTest.php` is a data provider over every `bundle_*`/`expected_*` pair. For each
  it runs `ResolutionEngine::decide()` and asserts:
  - `expectedHashing` — computed buckets per unit/layer.
  - `expectedAssignments` — resolved parameter values.
  - `expectedLayers` — per-layer resolution metadata (policy/allocation/unitKey/attributionOnly).

```bash
git submodule update --init --recursive
composer conformance
```

## Covered vectors

| Fixture | What it locks |
|---------|---------------|
| `bundle_basic` | Layered resolution, weighted selection, defaults fallback |
| `bundle_conditions` | All condition operators + nested dot-path access + AND semantics |
| `bundle_edge_policies` | Caller→bundle→policy priority, eligible bucket-range gating |
| `bundle_contextual` | Contextual-bandit scoring (`softmax`, action-probability floor) |
| `bundle_contextual_boundary` | Near-gridline softmax cases — detects cross-language `exp()` drift |
| `bundle_per_layer_unit_key` | Per-layer unit-key override (`bucket = -1` skip) + attributionOnly |
| `bundle_unicode` | SHA-256 v2 hashing over **UTF-8 bytes** (non-ASCII unit keys and layer IDs) |

## Hashing domain

The SHA-256 v2 assignment hash is computed over the **UTF-8 bytes** of a length-framed, domain-separated
input (`traffical:assignment:v2|u:<len>:<unitKeyValue>|l:<len>:<layerId>`), not UTF-16 code units. The PHP
engine uses the native `hash('sha256', …, true)` (raw binary) and folds the first 8 digest bytes (unsigned
big-endian) modulo `bucketCount` with overflow-safe base-256 arithmetic. ASCII keys are unaffected by the
byte-vs-code-unit distinction; the `bundle_unicode` vector pins the non-ASCII behavior across SDKs.

## Bundle vs. server mode

Conformance covers the **local engine** (bundle mode), which is the shared resolution surface. Server mode
(`POST /v1/resolve`) delegates the same engine to the edge worker:

- **Bundle mode** — zero per-decision network calls; resolution is in-process from a cached bundle. Lowest
  latency; the bundle refreshes on a TTL.
- **Server mode** — one cached `POST /v1/resolve` per request. Useful when you cannot ship the full bundle to
  the client or need always-fresh server-side allocations; pays a per-request network round trip.

Both produce identical assignments for the same config and context — that is exactly what the conformance
vectors guarantee.
