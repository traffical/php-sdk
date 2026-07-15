<?php

declare(strict_types=1);

namespace Traffical\Types;

use RuntimeException;

/**
 * Thrown at bundle-parse time (S8) when a config bundle is structurally
 * unusable — e.g. `bucketCount < 1`, or a missing/empty `hashing.unitKey`.
 *
 * A malformed bundle MUST be discarded rather than replace a good cached bundle:
 * the config sources and {@see \Traffical\Client::getBundle()} catch this and
 * fail open (keep last-good → localConfig → caller defaults), so the SDK never
 * crashes out of decide()/getParams() on a bad payload.
 */
final class MalformedBundleException extends RuntimeException
{
}
