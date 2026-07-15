<?php

declare(strict_types=1);

namespace Traffical\Tests\Support;

/**
 * A small, self-contained JSON Schema (draft-07 subset) validator, scoped to the
 * constructs actually used by `schemas/events.schema.json`:
 *   $ref (#/definitions/*), allOf, oneOf, type, required, properties,
 *   additionalProperties (bool or schema), enum, const, items,
 *   minimum / maximum / exclusiveMinimum.
 *
 * This avoids taking a network/composer dependency on a full validator just to
 * run the event-payload conformance vectors; the vectors themselves (with their
 * valid/invalid flags) are the check that this validator draws the line in the
 * right place. Not a general-purpose validator — only what the events schema
 * needs.
 */
final class JsonSchemaValidator
{
    /** @param array<string, mixed> $schema */
    public function __construct(private readonly array $schema)
    {
    }

    /**
     * @param array<string, mixed> $schemaJson
     */
    public static function fromArray(array $schemaJson): self
    {
        return new self($schemaJson);
    }

    public function isValid(mixed $value): bool
    {
        return $this->check($value, $this->schema);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function check(mixed $value, array $schema): bool
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $resolved = $this->resolveRef($schema['$ref']);
            if ($resolved !== null && !$this->check($value, $resolved)) {
                return false;
            }
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $sub) {
                if (!is_array($sub) || !$this->check($value, $sub)) {
                    return false;
                }
            }
        }

        if (isset($schema['oneOf']) && is_array($schema['oneOf'])) {
            $matches = 0;
            foreach ($schema['oneOf'] as $sub) {
                if (is_array($sub) && $this->check($value, $sub)) {
                    $matches++;
                }
            }
            if ($matches !== 1) {
                return false;
            }
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            return false;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            return false;
        }

        if (isset($schema['type']) && is_string($schema['type']) && !$this->checkType($value, $schema['type'])) {
            return false;
        }

        if (is_array($value) && !array_is_list($value)) {
            if (!$this->checkObject($value, $schema)) {
                return false;
            }
        }

        if (is_array($value) && array_is_list($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $item) {
                if (!$this->check($item, $schema['items'])) {
                    return false;
                }
            }
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && (is_int($schema['minimum']) || is_float($schema['minimum'])) && $value < $schema['minimum']) {
                return false;
            }
            if (isset($schema['maximum']) && (is_int($schema['maximum']) || is_float($schema['maximum'])) && $value > $schema['maximum']) {
                return false;
            }
            if (isset($schema['exclusiveMinimum']) && (is_int($schema['exclusiveMinimum']) || is_float($schema['exclusiveMinimum'])) && $value <= $schema['exclusiveMinimum']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $schema
     */
    private function checkObject(array $value, array $schema): bool
    {
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $req) {
                if (!array_key_exists((string) $req, $value)) {
                    return false;
                }
            }
        }

        $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
        foreach ($properties as $name => $propSchema) {
            if (array_key_exists((string) $name, $value) && is_array($propSchema)) {
                if (!$this->check($value[$name], $propSchema)) {
                    return false;
                }
            }
        }

        if (array_key_exists('additionalProperties', $schema)) {
            $ap = $schema['additionalProperties'];
            if ($ap === false) {
                foreach (array_keys($value) as $k) {
                    if (!array_key_exists((string) $k, $properties)) {
                        return false;
                    }
                }
            } elseif (is_array($ap)) {
                foreach ($value as $k => $v) {
                    if (!array_key_exists((string) $k, $properties) && !$this->check($v, $ap)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function checkType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && (!array_is_list($value) || $value === []),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRef(string $ref): ?array
    {
        if (!str_starts_with($ref, '#/')) {
            return null;
        }
        $segments = explode('/', substr($ref, 2));
        $current = $this->schema;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return is_array($current) ? $current : null;
    }
}
