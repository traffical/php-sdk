<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * Deterministic weighted selection using FNV-1a hashing. Used by both
 * per-entity resolution and contextual bandit scoring.
 */
final class WeightedSelection
{
    /**
     * Selects an index deterministically from weights using a seed string.
     *
     * @param list<float> $weights Weights (should sum to 1.0).
     */
    public static function select(array $weights, string $seed): int
    {
        $count = count($weights);
        if ($count <= 1) {
            return 0;
        }

        $hash = Fnv1a::hash($seed);
        $random = ($hash % 10000) / 10000;

        $cumulative = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $cumulative += $weights[$i];
            if ($random < $cumulative) {
                return $i;
            }
        }

        return $count - 1;
    }
}
