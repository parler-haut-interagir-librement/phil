<?php

declare(strict_types=1);

namespace Phil\FormLogic\Typing;

/**
 * What a variable holds, which says what a condition may compare it with.
 */
enum VariableType: string
{
    /** A text: compared for equality and membership with texts. */
    case Text = 'text';

    /** A number: compared and ordered with numbers, and computed with. */
    case Number = 'number';

    /** True or false: compared with `true` and `false`. */
    case Boolean = 'boolean';

    /** A day, `YYYY-MM-DD`: compared and ordered with days, whose text order is their order in time. */
    case Date = 'date';

    /** One of a list of choices: compared with those choices only. */
    case Choice = 'choice';

    /** Several of a list of choices: a choice is looked for in it with `in`. */
    case Choices = 'choices';

    /**
     * Whether it orders, for `<`, `<=`, `>`, `>=` and intervals.
     */
    public function orders(): bool
    {
        return self::Number === $this || self::Date === $this;
    }
}
