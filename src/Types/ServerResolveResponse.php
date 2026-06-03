<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Response from the /v1/resolve endpoint (server-evaluated mode).
 */
final class ServerResolveResponse
{
    /**
     * @param array<string, mixed> $assignments
     */
    public function __construct(
        public readonly string $decisionId,
        public readonly array $assignments,
        public readonly DecisionMetadata $metadata,
        public readonly string $stateVersion,
        public readonly ?int $suggestedRefreshMs = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $data['metadata'] ?? [];
        /** @var array<string, mixed> $assignments */
        $assignments = $data['assignments'] ?? [];

        return new self(
            decisionId: Json::str($data, 'decisionId'),
            assignments: $assignments,
            metadata: DecisionMetadata::fromArray($metadata),
            stateVersion: Json::str($data, 'stateVersion'),
            suggestedRefreshMs: Json::intOrNull($data, 'suggestedRefreshMs'),
        );
    }
}
