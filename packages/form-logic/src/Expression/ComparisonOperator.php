<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

/**
 * How a comparison compares, by its JsonLogic operator.
 */
enum ComparisonOperator: string
{
    /** Equal, the types converted as JavaScript's `==` does. */
    case Equal = '==';

    /** Not equal, the types converted as JavaScript's `!=` does. */
    case NotEqual = '!=';

    /** Equal and of the same type. */
    case StrictlyEqual = '===';

    /** Not equal, or not of the same type. */
    case StrictlyNotEqual = '!==';

    /** Before: texts in their JavaScript order, anything else as numbers. */
    case Less = '<';

    /** Before or equal. */
    case LessOrEqual = '<=';

    /** After. */
    case Greater = '>';

    /** After or equal. */
    case GreaterOrEqual = '>=';

    /**
     * Whether it orders its operands rather than tells them equal.
     */
    public function orders(): bool
    {
        return match ($this) {
            self::Less, self::LessOrEqual, self::Greater, self::GreaterOrEqual => true,
            default => false,
        };
    }
}
