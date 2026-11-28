<?php

declare(strict_types=1);

namespace Phil\Rules\Condition;

/**
 * A condition of a registered type, with its operands as written: `new Leaf('fact.equals', ['fact' => 'country',
 * 'value' => 'CA'])`. The operands are checked against the type's schema when the rule is built.
 */
final readonly class Leaf implements Condition
{
    /**
     * @param array<array-key, mixed> $operands
     */
    public function __construct(
        public string $type,
        public array $operands = [],
    ) {
    }
}
