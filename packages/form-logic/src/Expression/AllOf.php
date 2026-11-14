<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;

/**
 * JsonLogic's `and`: the first operand that is false, else the last one.
 */
final readonly class AllOf implements Expression
{
    /**
     * @param list<Expression> $operands at least one
     *
     * @throws InvalidLogic without an operand
     */
    public function __construct(public array $operands)
    {
        if ([] === $operands) {
            throw InvalidLogic::because('An "and" has at least one operand.');
        }
    }

    #[Override]
    public function jsonLogic(): array
    {
        return ['and' => array_map(static fn (Expression $operand): array|string|int|float|bool|null => $operand->jsonLogic(), $this->operands)];
    }

    #[Override]
    public function children(): array
    {
        return $this->operands;
    }
}
