<?php

declare(strict_types=1);

namespace Traffical\Http;

use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Resolves the PSR-18 client the transports send through, applying the
 * mandated per-request timeout (spec §"Mandatory timeouts") where the
 * underlying client can enforce it.
 *
 * PSR-18's {@see ClientInterface::sendRequest()} exposes no per-request
 * options, so a timeout can only be applied at client-construction time. When
 * the caller does not inject a client and Guzzle is installed, we build a
 * Guzzle client with `connect_timeout`/`timeout` set rather than defer to
 * discovery (which would yield a Guzzle client on platform defaults). When the
 * caller injects their own PSR-18 client we use it unchanged — an arbitrary
 * PSR-18 client offers no portable timeout hook, so honouring the value is a
 * documented limitation for injected clients.
 */
final class HttpClientFactory
{
    public static function resolve(?ClientInterface $injected, int $timeoutMs): ClientInterface
    {
        if ($injected !== null) {
            return $injected;
        }

        if (class_exists(GuzzleClient::class)) {
            $seconds = max(0, $timeoutMs) / 1000.0;

            return new GuzzleClient([
                'connect_timeout' => $seconds,
                'timeout' => $seconds,
            ]);
        }

        return Psr18ClientDiscovery::find();
    }
}
