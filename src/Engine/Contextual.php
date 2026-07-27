<?php

declare(strict_types=1);

namespace Traffical\Engine;

use Traffical\Types\BundleAllocation;
use Traffical\Types\BundleAllocationCoefficients;
use Traffical\Types\BundleContextualModel;
use Traffical\Types\BundlePolicy;

/**
 * Contextual bandit scoring. Pure functions for computing personalized
 * allocation probabilities from a trained linear model, mirroring the core-ts
 * pipeline byte-for-byte (same float operation order).
 */
final class Contextual
{
    /**
     * Computes the linear score for a single allocation given context features.
     *
     * @param array<string, mixed> $context
     */
    public static function computeAllocationScore(
        BundleAllocationCoefficients $coefficients,
        array $context,
    ): float {
        $score = $coefficients->intercept;

        foreach ($coefficients->numeric as $c) {
            $value = $context[$c->key] ?? null;
            $score += is_int($value) || is_float($value)
                ? $c->coef * (float) $value
                : $c->missing;
        }

        foreach ($coefficients->categorical as $c) {
            $value = $context[$c->key] ?? null;
            $strValue = $value !== null ? self::stringify($value) : null;
            $score += $strValue !== null && array_key_exists($strValue, $c->values)
                ? $c->values[$strValue]
                : $c->missing;
        }

        return $score;
    }

    /**
     * Applies softmax with temperature (gamma). Uses the numerically stable
     * variant: subtract max before exponentiation.
     *
     * @param list<float> $scores
     * @return list<float>
     */
    public static function softmaxProbabilities(array $scores, float $gamma): array
    {
        $n = count($scores);
        if ($n === 0) {
            return [];
        }
        if ($n === 1) {
            return [1.0];
        }

        $safeGamma = max($gamma, 1e-10);
        $scaled = array_map(static fn (float $s): float => $s / $safeGamma, $scores);
        $maxScaled = max($scaled);
        $exps = array_map(static fn (float $s): float => exp($s - $maxScaled), $scaled);
        $sumExp = array_sum($exps);

        return array_map(static fn (float $e): float => $e / $sumExp, $exps);
    }

    /**
     * Enforces a minimum probability floor on each allocation and renormalizes.
     *
     * @param list<float> $probs
     * @return list<float>
     */
    public static function applyProbabilityFloor(array $probs, float $floor): array
    {
        $n = count($probs);
        if ($n === 0) {
            return [];
        }
        if ($floor <= 0) {
            return $probs;
        }

        $maxFloor = 1.0 / $n;
        $effectiveFloor = min($floor, $maxFloor);

        $floored = array_map(static fn (float $p): float => max($p, $effectiveFloor), $probs);
        $sum = array_sum($floored);

        if ($sum === 0.0) {
            return array_fill(0, $n, 1 / $n);
        }

        return array_map(static fn (float $p): float => $p / $sum, $floored);
    }

    /**
     * Resolves a contextual policy to a specific allocation using the trained
     * model. Returns null if the policy has no model or no allocations.
     *
     * @param array<string, mixed> $context
     */
    public static function resolvePolicy(
        BundlePolicy $policy,
        array $context,
        string $unitKeyValue,
    ): ?BundleAllocation {
        return self::resolvePolicyWithProbability($policy, $context, $unitKeyValue)?->allocation;
    }

    /**
     * Like {@see resolvePolicy()} but also returns the floored-softmax
     * probability of the CHOSEN allocation — the propensity logged on layer
     * entries for off-policy (IPS/DR) training.
     *
     * @param array<string, mixed> $context
     */
    public static function resolvePolicyWithProbability(
        BundlePolicy $policy,
        array $context,
        string $unitKeyValue,
    ): ?AllocationSelection {
        $model = $policy->contextualModel;
        if ($model === null) {
            return null;
        }
        if (count($policy->allocations) === 0) {
            return null;
        }

        $scores = self::computeScores($model, $policy->allocations, $context);
        $probs = self::softmaxProbabilities($scores, $model->gamma);
        $floored = self::applyProbabilityFloor($probs, $model->actionProbabilityFloor);

        $seed = 'ctx:' . $unitKeyValue . ':' . $policy->id;
        $selectedIndex = WeightedSelection::select($floored, $seed);

        return new AllocationSelection(
            allocation: $policy->allocations[$selectedIndex],
            probability: $floored[$selectedIndex],
        );
    }

    /**
     * Raw score per allocation.
     *
     * Coefficients are keyed by allocation `key` — the stable identifier — with
     * `name` as the fallback for bundles produced before `key` existed. Keying
     * by `name` alone is the silent-failure mode this indirection exists to
     * prevent: the lookup misses for every allocation whose display name
     * differs from its key ("Treatment A" vs "treatment-a"), those arms score
     * `defaultAllocationScore`, and the trained model degrades to a uniform
     * softmax with nothing raised anywhere. Locked by the sdk-spec
     * `bundle_contextual_key_differs` vector.
     *
     * @param list<BundleAllocation> $allocations
     * @param array<string, mixed> $context
     * @return list<float>
     */
    private static function computeScores(
        BundleContextualModel $model,
        array $allocations,
        array $context,
    ): array {
        return array_map(
            function (BundleAllocation $alloc) use ($model, $context): float {
                $coef = $model->coefficients[$alloc->key ?? $alloc->name] ?? null;
                if ($coef === null) {
                    return $model->defaultAllocationScore;
                }

                return self::computeAllocationScore($coef, $context);
            },
            $allocations,
        );
    }

    /**
     * Canonical stringification (S2) of the scalar context values that can
     * appear as categorical features, so numeric feature values map to the
     * same trained-coefficient key across SDKs.
     */
    private static function stringify(mixed $value): string
    {
        return Strings::jsString($value);
    }
}
