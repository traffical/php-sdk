<?php

declare(strict_types=1);

namespace Traffical\Engine;

/**
 * Deterministic weighted selection using the SHA-256 v2 assignment hash. Used
 * by both per-entity resolution and contextual bandit scoring.
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

        $random = AssignmentHash::uniform(AssignmentHash::digest($seed));

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
