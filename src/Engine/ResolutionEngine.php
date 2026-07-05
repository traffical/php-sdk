<?php

declare(strict_types=1);

namespace Traffical\Engine;

use Traffical\Types\BundleAllocation;
use Traffical\Types\BundlePolicy;
use Traffical\Types\ConfigBundle;
use Traffical\Types\DecisionMetadata;
use Traffical\Types\DecisionResult;
use Traffical\Types\LayerResolution;

/**
 * Pure parameter resolution using layered config and policies.
 *
 * This is the conformance surface — a one-to-one port of the core-ts engine.
 * It performs no I/O: callers supply decision IDs and timestamps so the engine
 * stays deterministic and testable against the language-agnostic spec.
 */
final class ResolutionEngine
{
    /**
     * Resolves parameters with required defaults as fallback.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public static function resolveParameters(
        ?ConfigBundle $bundle,
        array $context,
        array $defaults,
        ?ResolveOptions $options = null,
    ): array {
        return self::resolveInternal($bundle, $context, $defaults, $options)->assignments;
    }

    /**
     * Makes a decision with full metadata for tracking.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     */
    public static function decide(
        ?ConfigBundle $bundle,
        array $context,
        array $defaults,
        ?ResolveOptions $options = null,
        string $decisionId = '',
        string $timestamp = '',
    ): DecisionResult {
        $result = self::resolveInternal($bundle, $context, $defaults, $options);

        $filteredContext = self::filterContext($context, $result->matchedPolicies);

        return new DecisionResult(
            decisionId: $decisionId,
            assignments: $result->assignments,
            metadata: new DecisionMetadata(
                timestamp: $timestamp,
                unitKeyValue: $result->unitKeyValue,
                layers: $result->layers,
                filteredContext: $filteredContext,
            ),
        );
    }

    /**
     * Extracts the project unit key value from context, or null if not found.
     *
     * @param array<string, mixed> $context
     */
    public static function getUnitKeyValue(ConfigBundle $bundle, array $context): ?string
    {
        $value = $context[$bundle->hashing->unitKey] ?? null;
        if ($value === null) {
            return null;
        }

        return self::stringify($value);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $defaults
     */
    private static function resolveInternal(
        ?ConfigBundle $bundle,
        array $context,
        array $defaults,
        ?ResolveOptions $options,
    ): ResolutionResult {
        /** @var array<string, mixed> $assignments */
        $assignments = $defaults;
        /** @var list<LayerResolution> $layers */
        $layers = [];
        /** @var list<BundlePolicy> $matchedPolicies */
        $matchedPolicies = [];

        if ($bundle === null) {
            return new ResolutionResult($assignments, '', $layers, $matchedPolicies);
        }

        $projectUnitKeyValue = self::getUnitKeyValue($bundle, $context) ?? '';

        $requestedKeys = array_fill_keys(array_keys($defaults), true);

        // Filter bundle parameters to only those requested, and apply their
        // bundle defaults (overrides caller defaults).
        $paramsByLayer = [];
        foreach ($bundle->parameters as $param) {
            if (!isset($requestedKeys[$param->key])) {
                continue;
            }
            if (array_key_exists($param->key, $assignments)) {
                $assignments[$param->key] = $param->default;
            }
            $paramsByLayer[$param->layerId][] = $param;
        }

        foreach ($bundle->layers as $layer) {
            $hasParams = !empty($paramsByLayer[$layer->id]);

            $layerUnitKey = $layer->unitKey;
            if ($layerUnitKey !== null) {
                $raw = $context[$layerUnitKey] ?? null;
                $layerUnitValue = $raw === null ? '' : self::stringify($raw);
            } else {
                $layerUnitValue = $projectUnitKeyValue;
            }

            if ($layerUnitValue === '') {
                $layers[] = new LayerResolution(
                    layerId: $layer->id,
                    bucket: -1,
                    unitKey: $layerUnitKey,
                    unitKeyValue: $layerUnitKey !== null ? '' : null,
                    attributionOnly: $hasParams ? null : true,
                );
                continue;
            }

            $bucket = Bucket::compute($layerUnitValue, $layer->id, $bundle->hashing->bucketCount);

            $matchedPolicy = null;
            $matchedAllocation = null;
            $matchedProbability = null;
            $matchedModelVersion = null;

            foreach ($layer->policies as $policy) {
                if ($policy->state !== 'running') {
                    continue;
                }

                if ($policy->eligibleBucketRange !== null) {
                    if ($bucket < $policy->eligibleBucketRange['start'] || $bucket > $policy->eligibleBucketRange['end']) {
                        continue;
                    }
                }

                if (!Conditions::evaluateAll($policy->conditions, $context)) {
                    continue;
                }

                // Contextual model scoring overrides bucket-based allocation.
                if ($policy->contextualModel !== null) {
                    $ctxSelection = Contextual::resolvePolicyWithProbability($policy, $context, $layerUnitValue);
                    if ($ctxSelection !== null) {
                        $matchedPolicy = $policy;
                        $matchedAllocation = $ctxSelection->allocation;
                        $matchedProbability = $ctxSelection->probability;
                        // Prefer the bundle model's generatedAt; fall back to
                        // the policy stateVersion for bundles that predate the
                        // contextualModel timestamp.
                        $matchedModelVersion = $policy->contextualModel->modelVersion ?? $policy->stateVersion;
                        $matchedPolicies[] = $policy;
                        if ($hasParams) {
                            self::applyOverrides($assignments, $ctxSelection->allocation->overrides);
                        }
                        break;
                    }
                }

                if ($policy->entityConfig !== null && $policy->entityConfig->resolutionMode === 'bundle') {
                    $result = self::resolvePerEntityPolicy($bundle, $policy, $context, $layerUnitValue);
                    if ($result !== null) {
                        $matchedPolicy = $policy;
                        $matchedAllocation = $result->allocation;
                        $matchedProbability = $result->probability;
                        $matchedPolicies[] = $policy;
                        if ($hasParams && $policy->entityConfig->dynamicAllocations === null) {
                            self::applyOverrides($assignments, $result->allocation->overrides);
                        }
                        break;
                    }
                } elseif ($policy->entityConfig !== null && $policy->entityConfig->resolutionMode === 'edge') {
                    $edgeResult = $options?->edgeResults[$policy->id] ?? null;
                    if ($edgeResult !== null) {
                        $matchedPolicy = $policy;
                        $matchedPolicies[] = $policy;

                        if ($policy->entityConfig->dynamicAllocations !== null) {
                            $matchedAllocation = new BundleAllocation(
                                id: $policy->id . '_dynamic_' . $edgeResult->allocationIndex,
                                name: (string) $edgeResult->allocationIndex,
                                bucketRange: [0, 0],
                                overrides: [],
                            );
                        } elseif (isset($policy->allocations[$edgeResult->allocationIndex])) {
                            $matchedAllocation = $policy->allocations[$edgeResult->allocationIndex];
                            if ($hasParams) {
                                self::applyOverrides($assignments, $matchedAllocation->overrides);
                            }
                        }
                        break;
                    }
                    continue;
                } else {
                    $allocation = Bucket::findMatchingAllocation($bucket, $policy->allocations);
                    if ($allocation !== null) {
                        $matchedPolicy = $policy;
                        $matchedAllocation = $allocation;
                        // Bucket-range adaptive policies (thompson_bernoulli /
                        // epsilon_greedy / ucb1): the propensity is the chosen
                        // allocation's bucket-range share. Static policies
                        // omit the probability entirely.
                        if ($policy->kind === 'adaptive' && $bundle->hashing->bucketCount > 0) {
                            $matchedProbability =
                                ($allocation->bucketRange[1] - $allocation->bucketRange[0] + 1)
                                / $bundle->hashing->bucketCount;
                        }
                        $matchedPolicies[] = $policy;
                        if ($hasParams) {
                            self::applyOverrides($assignments, $allocation->overrides);
                        }
                        break;
                    }
                }
            }

            $layers[] = new LayerResolution(
                layerId: $layer->id,
                bucket: $bucket,
                policyId: $matchedPolicy?->id,
                policyKey: $matchedPolicy?->key,
                allocationId: $matchedAllocation?->id,
                allocationName: $matchedAllocation?->name,
                allocationKey: $matchedAllocation?->key,
                unitKey: $layerUnitKey,
                unitKeyValue: $layerUnitKey !== null ? $layerUnitValue : null,
                attributionOnly: $hasParams ? null : true,
                // The events schema requires probability in (0, 1]; omit (never
                // clamp) anything outside that range — the degenerate zero-weight
                // fallback of WeightedSelection or malformed entity weights.
                probability: $matchedProbability !== null && $matchedProbability > 0 && $matchedProbability <= 1
                    ? $matchedProbability
                    : null,
                modelVersion: $matchedModelVersion,
            );
        }

        return new ResolutionResult($assignments, $projectUnitKeyValue, $layers, $matchedPolicies);
    }

    /**
     * @param array<string, mixed> $assignments
     * @param array<string, mixed> $overrides
     */
    private static function applyOverrides(array &$assignments, array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $assignments)) {
                $assignments[$key] = $value;
            }
        }
    }

    /**
     * Resolves a per-entity (bundle-mode) policy using weighted selection.
     * The returned selection carries the weight the SDK actually used, which
     * is logged as the propensity of the choice.
     *
     * @param array<string, mixed> $context
     */
    private static function resolvePerEntityPolicy(
        ConfigBundle $bundle,
        BundlePolicy $policy,
        array $context,
        string $unitKeyValue,
    ): ?AllocationSelection {
        $entityConfig = $policy->entityConfig;
        if ($entityConfig === null) {
            return null;
        }

        $entityId = self::buildEntityId($entityConfig->entityKeys, $context);
        if ($entityId === null) {
            return null;
        }

        if ($entityConfig->dynamicAllocations !== null) {
            $countKey = $entityConfig->dynamicAllocations['countKey'];
            $count = $context[$countKey] ?? null;
            if (!(is_int($count) || is_float($count)) || $count <= 0) {
                return null;
            }
            $allocationCount = (int) $count;
            $allocations = [];
            for ($i = 0; $i < $allocationCount; $i++) {
                $allocations[] = new BundleAllocation(
                    id: $policy->id . '_dynamic_' . $i,
                    name: (string) $i,
                    bucketRange: [0, 0],
                    overrides: [],
                );
            }
        } else {
            $allocations = $policy->allocations;
            $allocationCount = count($allocations);
        }

        if ($allocationCount === 0) {
            return null;
        }

        $weights = self::getEntityWeights($bundle, $policy->id, $entityId, $allocationCount);
        $seed = $entityId . ':' . $unitKeyValue . ':' . $policy->id;
        $selectedIndex = WeightedSelection::select($weights, $seed);

        return new AllocationSelection(
            allocation: $allocations[$selectedIndex],
            probability: $weights[$selectedIndex],
        );
    }

    /**
     * @param list<string> $entityKeys
     * @param array<string, mixed> $context
     */
    private static function buildEntityId(array $entityKeys, array $context): ?string
    {
        $parts = [];
        foreach ($entityKeys as $key) {
            $value = $context[$key] ?? null;
            if ($value === null) {
                return null;
            }
            $parts[] = self::stringify($value);
        }

        return implode('_', $parts);
    }

    /**
     * @return list<float>
     */
    private static function getEntityWeights(
        ConfigBundle $bundle,
        string $policyId,
        string $entityId,
        int $allocationCount,
    ): array {
        $policyState = $bundle->entityState[$policyId] ?? null;
        if ($policyState === null) {
            return self::uniformWeights($allocationCount);
        }

        $entityWeights = $policyState->entities[$entityId] ?? null;
        if ($entityWeights !== null && count($entityWeights->weights) === $allocationCount) {
            return $entityWeights->weights;
        }

        if ($policyState->global !== null && count($policyState->global->weights) === $allocationCount) {
            return $policyState->global->weights;
        }

        return self::uniformWeights($allocationCount);
    }

    /**
     * @return list<float>
     */
    private static function uniformWeights(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        return array_fill(0, $count, 1 / $count);
    }

    /**
     * Collects the union of contextLogging.allowedFields from matched policies
     * and filters the context to just those fields.
     *
     * @param array<string, mixed> $context
     * @param list<BundlePolicy> $policies
     * @return array<string, mixed>|null
     */
    private static function filterContext(array $context, array $policies): ?array
    {
        $allowedFields = [];
        foreach ($policies as $policy) {
            if ($policy->contextLogging !== null) {
                foreach ($policy->contextLogging->allowedFields as $field) {
                    $allowedFields[$field] = true;
                }
            }
        }

        if (count($allowedFields) === 0) {
            return null;
        }

        $filtered = [];
        foreach (array_keys($allowedFields) as $field) {
            if (array_key_exists($field, $context)) {
                $filtered[$field] = $context[$field];
            }
        }

        return count($filtered) > 0 ? $filtered : null;
    }

    private static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
