<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * Sentinel representing a JavaScript `undefined` value.
 *
 * PHP has no distinction between "missing key" and "key set to null", but the
 * core-ts engine does (e.g. `eq` against `null` matches an explicit null but
 * not a missing field). Nested context lookups return this singleton when a
 * field is absent so condition evaluation can replicate JS semantics exactly.
 */
final class Undefined
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
