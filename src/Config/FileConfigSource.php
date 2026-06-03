<?php

declare(strict_types=1);

namespace Traffical\Config;

use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Traffical\Types\ConfigBundle;

/**
 * A config source that reads a bundle from a JSON file on disk. Handy for
 * local development, air-gapped deployments, and CI fixtures.
 */
final class FileConfigSource implements ConfigSource
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly string $path,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function load(): ?ConfigBundle
    {
        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            $this->logger->warning('[Traffical] Could not read config file', ['path' => $this->path]);

            return null;
        }

        try {
            return ConfigBundle::fromJson($contents);
        } catch (JsonException $e) {
            $this->logger->warning('[Traffical] Invalid config file JSON', [
                'path' => $this->path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
