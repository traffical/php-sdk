<?php

declare(strict_types=1);

namespace Traffical\OpenFeature;

use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\implementation\provider\ResolutionDetailsBuilder;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\provider\Provider;
use OpenFeature\interfaces\provider\ResolutionDetails;
use Traffical\Client;

/**
 * Optional OpenFeature provider backed by the Traffical {@see Client}.
 *
 * Each flag evaluation maps to a single-key `getParams()` call: the flag key is
 * the parameter name and the OpenFeature default is the fallback. The
 * EvaluationContext is flattened into the Traffical context (the targeting key
 * is exposed as `targetingKey`).
 *
 * Requires `open-feature/sdk`. Register with:
 *   OpenFeatureAPI::getInstance()->setProvider(new TrafficalProvider($client));
 */
final class TrafficalProvider extends AbstractProvider implements Provider
{
    protected static string $NAME = 'TrafficalProvider';

    public function __construct(private readonly Client $client)
    {
    }

    public function resolveBooleanValue(
        string $flagKey,
        bool $defaultValue,
        ?EvaluationContext $context = null,
    ): ResolutionDetails {
        return $this->resolve($flagKey, $defaultValue, $context);
    }

    public function resolveStringValue(
        string $flagKey,
        string $defaultValue,
        ?EvaluationContext $context = null,
    ): ResolutionDetails {
        return $this->resolve($flagKey, $defaultValue, $context);
    }

    public function resolveIntegerValue(
        string $flagKey,
        int $defaultValue,
        ?EvaluationContext $context = null,
    ): ResolutionDetails {
        return $this->resolve($flagKey, $defaultValue, $context);
    }

    public function resolveFloatValue(
        string $flagKey,
        float $defaultValue,
        ?EvaluationContext $context = null,
    ): ResolutionDetails {
        return $this->resolve($flagKey, $defaultValue, $context);
    }

    /**
     * @param mixed $defaultValue
     */
    public function resolveObjectValue(
        string $flagKey,
        $defaultValue,
        ?EvaluationContext $context = null,
    ): ResolutionDetails {
        return $this->resolve($flagKey, $defaultValue, $context);
    }

    /**
     * @param mixed $defaultValue
     */
    private function resolve(string $flagKey, $defaultValue, ?EvaluationContext $context): ResolutionDetails
    {
        $ctx = $this->contextToArray($context);
        $params = $this->client->getParams($ctx, [$flagKey => $defaultValue]);
        $value = array_key_exists($flagKey, $params) ? $params[$flagKey] : $defaultValue;

        return (new ResolutionDetailsBuilder())
            ->withValue($value)
            ->build();
    }

    /**
     * @return array<string, mixed>
     */
    private function contextToArray(?EvaluationContext $context): array
    {
        if ($context === null) {
            return [];
        }

        $attributes = $context->getAttributes()->toArray();
        $targetingKey = $context->getTargetingKey();
        if ($targetingKey !== null && $targetingKey !== '') {
            $attributes['targetingKey'] = $targetingKey;
        }

        return $attributes;
    }
}
