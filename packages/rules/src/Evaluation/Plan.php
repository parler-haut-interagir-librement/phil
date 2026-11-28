<?php

declare(strict_types=1);

namespace Phil\Rules\Evaluation;

use Phil\Rules\Operand\Operands;
use Phil\Rules\Type\ConditionType;

/**
 * A checked condition, ready to evaluate: a leaf keeps its type and its read operands, a composite its children.
 *
 * @internal
 */
final readonly class Plan
{
    /**
     * @param list<Plan> $children
     */
    private function __construct(
        public NodeKind $kind,
        public array $children = [],
        public ?ConditionType $type = null,
        public ?Operands $operands = null,
        public ?string $typeName = null,
    ) {
    }

    /**
     * @param list<Plan> $children
     */
    public static function composite(NodeKind $kind, array $children): self
    {
        return new self($kind, $children);
    }

    public static function leaf(ConditionType $type, Operands $operands, string $typeName): self
    {
        return new self(NodeKind::Leaf, type: $type, operands: $operands, typeName: $typeName);
    }
}
