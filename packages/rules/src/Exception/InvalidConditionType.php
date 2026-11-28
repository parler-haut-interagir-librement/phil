<?php

declare(strict_types=1);

namespace Phil\Rules\Exception;

use LogicException;

/**
 * A condition type that cannot be registered: a name outside the stable form, a name taken twice, or a schema that
 * declares an operand twice or badly.
 */
final class InvalidConditionType extends LogicException implements RulesException
{
    public static function name(string $name): self
    {
        return new self(\sprintf('"%s" is not a condition type name: lowercase words of letters and digits joined by dots, dashes or underscores, at most 64 bytes.', InvalidRule::shown($name)));
    }

    public static function duplicate(string $name): self
    {
        return new self(\sprintf('The condition type "%s" is registered twice.', $name));
    }

    public static function duplicateOperand(string $name): self
    {
        return new self(\sprintf('The operand "%s" is declared twice.', $name));
    }

    public static function operandName(string $name): self
    {
        return new self(\sprintf('"%s" is not an operand name: a lowercase word of letters, digits and underscores, at most 64 bytes.', InvalidRule::shown($name)));
    }

    public static function choices(string $name): self
    {
        return new self(\sprintf('The operand "%s" needs at least one choice, each a non-empty text given once.', $name));
    }
}
