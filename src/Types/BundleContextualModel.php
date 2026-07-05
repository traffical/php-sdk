<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Contextual bandit model in the bundle. Used for linear_contextual policies.
 */
final class BundleContextualModel
{
    /**
     * @param array<string, BundleAllocationCoefficients> $coefficients Keyed by allocation name.
     */
    public function __construct(
        /** Softmax temperature. Lower = more deterministic. */
        public readonly float $gamma,
        /** Minimum probability for any allocation (ensures exploration). */
        public readonly float $actionProbabilityFloor,
        /** Default score for allocations without trained coefficients. */
        public readonly float $defaultAllocationScore,
        public readonly array $coefficients,
        /**
         * Model timestamp of the coefficients — the bundle's `generatedAt`
         * (trainingSummary.generatedAt), with `modelVersion` accepted as an
         * alias. Emitted on exposure/decision layer entries as `modelVersion`;
         * when the bundle carries neither, the emission site falls back to the
         * policy's `stateVersion`.
         */
        public readonly ?string $modelVersion = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $coefficients = [];
        foreach (Json::arr($data, 'coefficients') as $name => $coef) {
            if (is_array($coef)) {
                $coefficients[(string) $name] = BundleAllocationCoefficients::fromArray($coef);
            }
        }

        return new self(
            gamma: Json::float($data, 'gamma'),
            actionProbabilityFloor: Json::float($data, 'actionProbabilityFloor'),
            defaultAllocationScore: Json::float($data, 'defaultAllocationScore'),
            coefficients: $coefficients,
            modelVersion: Json::strOrNull($data, 'generatedAt') ?? Json::strOrNull($data, 'modelVersion'),
        );
    }
}
