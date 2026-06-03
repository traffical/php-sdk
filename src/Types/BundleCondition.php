<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Condition in the bundle. Context predicate evaluated for policy eligibility.
 */
final class BundleCondition
{
    public function __construct(
        /** Context field to evaluate (dot notation supported). */
        public readonly string $field,
        /**
         * Comparison operator: eq | neq | in | nin | gt | gte | lt | lte |
         * contains | startsWith | endsWith | regex | exists | notExists.
         */
        public readonly string $op,
        /** Single value for binary operators. */
        public readonly mixed $value = null,
        /**
         * Multiple values for "in"/"nin" operators.
         *
         * @var list<mixed>|null
         */
        public readonly ?array $values = null,
    ) {
    }

    /**
     * @param array{field: string, op: string, value?: mixed, values?: list<mixed>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            field: $data['field'],
            op: $data['op'],
            value: $data['value'] ?? null,
            values: $data['values'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['field' => $this->field, 'op' => $this->op];
        if ($this->value !== null) {
            $out['value'] = $this->value;
        }
        if ($this->values !== null) {
            $out['values'] = $this->values;
        }

        return $out;
    }
}
