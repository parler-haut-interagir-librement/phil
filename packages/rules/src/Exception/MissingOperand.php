<?php

declare(strict_types=1);

namespace Phil\Rules\Exception;

use LogicException;

/**
 * An evaluator that reads an operand its schema does not give, or reads it as another kind: a mistake in the code of a
 * condition type, never in a rule.
 */
final class MissingOperand extends LogicException implements RulesException
{
    public static function of(string $name, string $expected): self
    {
        return new self(\sprintf('The operand "%s" is not %s in these operands.', $name, $expected));
    }
}
