<?php

declare(strict_types=1);

namespace Traffical\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Traffical\Symfony\DependencyInjection\TrafficalExtension;

/**
 * Symfony integration. Registers the Traffical {@see \Traffical\Client} service
 * configured under the `traffical` config key. Enable in `config/bundles.php`:
 *
 *   Traffical\Symfony\TrafficalBundle::class => ['all' => true],
 */
final class TrafficalBundle extends Bundle
{
    public function getContainerExtension(): TrafficalExtension
    {
        return new TrafficalExtension();
    }
}
