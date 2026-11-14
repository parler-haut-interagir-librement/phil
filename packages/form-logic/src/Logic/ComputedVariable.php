<?php

declare(strict_types=1);

namespace Phil\FormLogic\Logic;

use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\Variable;
use Phil\FormLogic\Typing\VariableType;

/**
 * A value computed from the answer — a total, a score, a category —, stored with it, and readable by the conditions
 * and the computed variables that come after it.
 */
final readonly class ComputedVariable
{
    /**
     * @param VariableType $type what the expression gives, which the conditions after it are checked against
     *
     * @throws InvalidLogic when the name is not a variable name, or the type a choice
     */
    public function __construct(public string $name, public Expression $expression, public VariableType $type = VariableType::Number)
    {
        Variable::checkName($name);

        if (VariableType::Choice === $type || VariableType::Choices === $type) {
            throw InvalidLogic::because(\sprintf('The computed variable "%s" gives a number, a text, a boolean or a day.', $name));
        }
    }
}
