<?php

declare(strict_types=1);

/**
 * Writing a custom plugin.
 *
 * Plugins hook into the SDK lifecycle. Extend AbstractPlugin (a no-op base) and
 * override only the hooks you need. Cancelable hooks (onExposure/onTrack) return
 * false to drop the event; onBeforeDecision can enrich the context.
 */

require __DIR__ . '/../vendor/autoload.php';

use Traffical\Client;
use Traffical\ClientOptions;
use Traffical\Plugins\AbstractPlugin;
use Traffical\Types\DecisionResult;

final class StderrAuditPlugin extends AbstractPlugin
{
    public function name(): string
    {
        return 'stderr-audit';
    }

    public function priority(): int
    {
        // Higher priority runs earlier.
        return 500;
    }

    /**
     * Enrich every decision context with a server region.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function onBeforeDecision(array $context): array
    {
        $context['region'] = getenv('AWS_REGION') ?: 'local';

        return $context;
    }

    public function onDecision(DecisionResult $decision): void
    {
        fwrite(STDERR, sprintf(
            "[audit] decision=%s assignments=%s\n",
            $decision->decisionId,
            json_encode($decision->assignments),
        ));
    }
}

$client = new Client(new ClientOptions(
    orgId: 'org_demo',
    projectId: 'prj_demo',
    env: 'production',
    apiKey: 'sdk_demo',
    plugins: [new StderrAuditPlugin()],
));

$decision = $client->decide(['userId' => 'user-abc'], ['hero_variant' => 'control']);
$client->trackExposure($decision);
$client->flushEvents();
