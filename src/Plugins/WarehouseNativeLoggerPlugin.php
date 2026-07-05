<?php

declare(strict_types=1);

namespace Traffical\Plugins;

use Traffical\Id\IdGenerator;
use Traffical\Types\AssignmentLogEntry;
use Traffical\Types\AssignmentType;
use Traffical\Types\ExposureEvent;
use Traffical\Warehouse\AssignmentLogger;

/**
 * Plugin route for warehouse-native assignment logging. Listens to exposure
 * events and forwards a per-layer {@see AssignmentLogEntry} to an
 * {@see AssignmentLogger} (e.g. {@see \Traffical\Warehouse\WarehouseNativeLogger}).
 *
 * This complements the client's `assignmentLogger` option for users who prefer
 * the plugin model. Prefer one route or the other to avoid double counting.
 */
final class WarehouseNativeLoggerPlugin extends AbstractPlugin
{
    private readonly IdGenerator $ids;

    public function __construct(
        private readonly AssignmentLogger $logger,
        private readonly ?string $sdkName = 'php',
        private readonly ?string $sdkVersion = null,
        ?IdGenerator $ids = null,
    ) {
        $this->ids = $ids ?? new IdGenerator();
    }

    public function name(): string
    {
        return 'warehouse-native-logger';
    }

    public function onExposure(ExposureEvent $event): bool
    {
        $properties = $event->context;

        foreach ($event->layers as $layer) {
            if ($layer->policyId === null || $layer->allocationName === null) {
                continue;
            }

            $this->logger->log(new AssignmentLogEntry(
                unitKey: $event->unitKey,
                policyId: $layer->policyId,
                allocationName: $layer->allocationName,
                timestamp: $event->timestamp,
                layerId: $layer->layerId,
                orgId: $event->orgId,
                projectId: $event->projectId,
                env: $event->env,
                type: AssignmentType::Exposure,
                policyKey: $layer->policyKey,
                allocationKey: $layer->allocationKey,
                allocationId: $layer->allocationId,
                sdkName: $this->sdkName,
                sdkVersion: $this->sdkVersion,
                properties: $properties,
                decisionId: $event->decisionId,
                anonymousId: null,
                id: $this->ids->assignment(),
                bucket: $layer->bucket >= 0 ? $layer->bucket : null,
                probability: $layer->probability,
                modelVersion: $layer->modelVersion,
                configVersion: $event->configVersion,
            ));
        }

        return true;
    }
}
