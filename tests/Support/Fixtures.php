<?php

declare(strict_types=1);

namespace Traffical\Tests\Support;

use RuntimeException;

/**
 * Locates and loads the sdk-spec conformance test vectors.
 *
 * Resolution order:
 *  1. The TRAFFICAL_SDK_SPEC_FIXTURES environment variable (CI override).
 *  2. The vendored git submodule at tests/sdk-spec.
 *  3. The sibling sdk-spec checkout in the monorepo.
 */
final class Fixtures
{
    public static function dir(): string
    {
        $candidates = [];
        $env = getenv('TRAFFICAL_SDK_SPEC_FIXTURES');
        if (is_string($env) && $env !== '') {
            $candidates[] = $env;
        }
        $candidates[] = __DIR__ . '/../sdk-spec/test-vectors/fixtures';
        $candidates[] = __DIR__ . '/../../../sdk-spec/test-vectors/fixtures';

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return rtrim($candidate, '/');
            }
        }

        throw new RuntimeException(
            'Could not locate sdk-spec fixtures. Run `git submodule update --init` ' .
            'or set TRAFFICAL_SDK_SPEC_FIXTURES.',
        );
    }

    /**
     * True when the pinned sdk-spec carries this fixture.
     *
     * The spec is a submodule pinned to a published tag, so a vector added to
     * the spec is listed here before the pin advances. Callers skip rather
     * than error on those, and the vector starts enforcing on its own the
     * moment the pin moves — the alternative is a red suite that says nothing
     * about the SDK.
     */
    public static function has(string $name): bool
    {
        return is_file(self::dir() . '/' . $name);
    }

    /**
     * @return array<string, mixed>
     */
    public static function load(string $name): array
    {
        $path = self::dir() . '/' . $name;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read fixture: {$path}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }
}
