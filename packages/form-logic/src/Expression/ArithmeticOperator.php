<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

/**
 * What an arithmetic operation computes, by its JsonLogic operator.
 */
enum ArithmeticOperator: string
{
    /** The sum of one or more operands. */
    case Add = '+';

    /** The second operand taken from the first, or the opposite of a single one. */
    case Subtract = '-';

    /** The product of one or more operands. */
    case Multiply = '*';

    /** The first operand divided by the second; `null` when the second is 0. */
    case Divide = '/';

    /** The remainder of the first operand divided by the second, of the sign of the first; `null` when the second is 0. */
    case Modulo = '%';

    /** The smallest of one or more operands. */
    case Min = 'min';

    /** The largest of one or more operands. */
    case Max = 'max';

    /**
     * The fewest operands it takes.
     */
    public function minOperands(): int
    {
        return match ($this) {
            self::Divide, self::Modulo => 2,
            default => 1,
        };
    }

    /**
     * The most operands it takes; null: any number.
     */
    public function maxOperands(): ?int
    {
        return match ($this) {
            self::Subtract, self::Divide, self::Modulo => 2,
            default => null,
        };
    }
}
