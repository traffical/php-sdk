<?php

declare(strict_types=1);

namespace Traffical\Types;

use Traffical\Support\Json;

/**
 * Track event — records user behavior/conversion.
 */
final class TrackEvent implements TrackableEvent
{
    /**
     * @param array<string, mixed>|null $properties
     * @param array<string, float>|null $values
     * @param list<TrackAttribution>|null $attribution
     */
    public function __construct(
        public readonly string $event,
        public readonly string $orgId,
        public readonly string $projectId,
        public readonly string $env,
        public readonly string $unitKey,
        public readonly string $timestamp,
        public readonly ?float $value = null,
        public readonly ?array $properties = null,
        public readonly ?string $decisionId = null,
        public readonly ?array $values = null,
        public readonly ?array $attribution = null,
        public readonly ?string $id = null,
        public readonly ?string $sdkName = null,
        public readonly ?string $sdkVersion = null,
        /** For delayed events: the original event time (ISO 8601). */
        public readonly ?string $eventTimestamp = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'type' => 'track',
            'orgId' => $this->orgId,
            'projectId' => $this->projectId,
            'env' => $this->env,
            'unitKey' => $this->unitKey,
            'timestamp' => $this->timestamp,
            'event' => $this->event,
        ];
        // Conditionally emit optional string fields so a null never serializes
        // as a schema-invalid `"id": null`.
        if ($this->id !== null) {
            $out['id'] = $this->id;
        }
        if ($this->sdkName !== null) {
            $out['sdkName'] = $this->sdkName;
        }
        if ($this->sdkVersion !== null) {
            $out['sdkVersion'] = $this->sdkVersion;
        }
        if ($this->value !== null) {
            $out['value'] = $this->value;
        }
        if ($this->properties !== null) {
            $out['properties'] = Json::map($this->properties);
        }
        if ($this->decisionId !== null) {
            $out['decisionId'] = $this->decisionId;
        }
        if ($this->values !== null) {
            $out['values'] = Json::map($this->values);
        }
        if ($this->eventTimestamp !== null) {
            $out['eventTimestamp'] = $this->eventTimestamp;
        }
        if ($this->attribution !== null) {
            $out['attribution'] = array_map(
                static fn (TrackAttribution $a): array => $a->toArray(),
                $this->attribution,
            );
        }

        return $out;
    }
}
