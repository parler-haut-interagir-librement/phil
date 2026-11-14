<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;

/**
 * Simple arithmetic, for the computed variables: `{"+": [{"var": "adults"}, {"var": "children"}]}`. The operands are
 * read as numbers; a result that is not a finite number is `null`.
 */
final readonly class Arithmetic implements Expression
{
    /**
     * @param list<Expression> $operands as many as the operator takes
     *
     * @throws InvalidLogic when the operator does not take that many operands
     */
    public function __construct(public ArithmeticOperator $operator, public array $operands)
    {
        $count = \count($operands);
        $max = $operator->maxOperands();

        if ($count < $operator->minOperands() || (null !== $max && $count > $max)) {
            throw InvalidLogic::because(\sprintf('A "%s" takes %s operands.', $operator->value, match ($operator) {
                ArithmeticOperator::Subtract => '1 or 2', ArithmeticOperator::Divide, ArithmeticOperator::Modulo => '2', default => '1 or more',
            }));
        }
    }

    #[Override]
    public function jsonLogic(): array
    {
        return [$this->operator->value => array_map(static fn (Expression $operand): array|string|int|float|bool|null => $operand->jsonLogic(), $this->operands)];
    }

    #[Override]
    public function children(): array
    {
        return $this->operands;
    }
}
