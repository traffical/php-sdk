<?php

declare(strict_types=1);

namespace Traffical\Engine;

use Traffical\Types\BundleCondition;

/**
 * Condition evaluation. Conditions are AND-ed: all must match for a policy to
 * apply. Mirrors core-ts semantics including JS strict-equality behavior and
 * dot-path nested access.
 */
final class Conditions
{
    /**
     * Evaluates all conditions against a context (AND logic). Empty = match.
     *
     * @param list<BundleCondition> $conditions
     * @param array<string, mixed> $context
     */
    public static function evaluateAll(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (!self::evaluate($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluates a single condition against a context.
     *
     * @param array<string, mixed> $context
     */
    public static function evaluate(BundleCondition $condition, array $context): bool
    {
        $contextValue = self::getNestedValue($context, $condition->field);
        $value = $condition->value;
        $values = $condition->values;

        switch ($condition->op) {
            case 'eq':
                return self::jsStrictEquals($contextValue, $value);

            case 'neq':
                return !self::jsStrictEquals($contextValue, $value);

            case 'in':
                if (!is_array($values)) {
                    return false;
                }

                return self::includes($values, $contextValue);

            case 'nin':
                if (!is_array($values)) {
                    return true;
                }

                return !self::includes($values, $contextValue);

            case 'gt':
                return self::isNumber($contextValue) && is_numeric($value)
                    && (float) $contextValue > (float) $value;

            case 'gte':
                return self::isNumber($contextValue) && is_numeric($value)
                    && (float) $contextValue >= (float) $value;

            case 'lt':
                return self::isNumber($contextValue) && is_numeric($value)
                    && (float) $contextValue < (float) $value;

            case 'lte':
                return self::isNumber($contextValue) && is_numeric($value)
                    && (float) $contextValue <= (float) $value;

            case 'contains':
                return is_string($contextValue) && is_string($value)
                    && str_contains($contextValue, $value);

            case 'startsWith':
                return is_string($contextValue) && is_string($value)
                    && str_starts_with($contextValue, $value);

            case 'endsWith':
                return is_string($contextValue) && is_string($value)
                    && str_ends_with($contextValue, $value);

            case 'regex':
                if (!is_string($contextValue) || !is_string($value)) {
                    return false;
                }
                $delimiter = "\x01";
                $result = @preg_match($delimiter . $value . $delimiter, $contextValue);

                return $result === 1;

            case 'exists':
                return !($contextValue instanceof Undefined) && $contextValue !== null;

            case 'notExists':
                return $contextValue instanceof Undefined || $contextValue === null;

            default:
                return false;
        }
    }

    /**
     * Gets a nested value using dot notation. Returns the {@see Undefined}
     * sentinel when any path segment is missing (JS `undefined`).
     *
     * @param array<string, mixed> $context
     */
    public static function getNestedValue(array $context, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $context;

        foreach ($parts as $part) {
            if ($current instanceof Undefined || $current === null) {
                return Undefined::instance();
            }

            if (is_array($current)) {
                if (!array_key_exists($part, $current)) {
                    return Undefined::instance();
                }
                $current = $current[$part];
            } else {
                return Undefined::instance();
            }
        }

        return $current;
    }

    /**
     * Replicates JavaScript `===` for the value types present in bundles:
     * numbers compare by value (JS numbers are all doubles), strings/bools/null
     * by identity, and objects/arrays are never equal (reference semantics).
     */
    private static function jsStrictEquals(mixed $a, mixed $b): bool
    {
        if ($a instanceof Undefined || $b instanceof Undefined) {
            return false;
        }
        if (self::isNumber($a) && self::isNumber($b)) {
            return (float) $a === (float) $b;
        }
        if (self::isNumber($a) || self::isNumber($b)) {
            return false;
        }
        if (is_array($a) || is_array($b)) {
            // JS object/array equality is by reference; bundle-decoded arrays are
            // distinct objects, so they never compare equal.
            return false;
        }

        return $a === $b;
    }

    /**
     * Array.includes() with JS strict-equality semantics.
     *
     * @param list<mixed> $haystack
     */
    private static function includes(array $haystack, mixed $needle): bool
    {
        foreach ($haystack as $candidate) {
            if (self::jsStrictEquals($needle, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @phpstan-assert-if-true int|float $value
     */
    private static function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }
}
