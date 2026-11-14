<?php

declare(strict_types=1);

namespace Phil\FormLogic\Typing;

use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\Variable;

/**
 * A variable a condition may read: its name, its type, and for a choice the values it takes.
 */
final readonly class VariableDefinition
{
    /**
     * @param list<string> $choices the values of a `Choice` or `Choices` variable, at least one; none for another type
     *
     * @throws InvalidLogic when the name is invalid, or the choices do not fit the type
     */
    public function __construct(public string $name, public VariableType $type, public array $choices = [])
    {
        Variable::checkName($name);
        $chooses = VariableType::Choice === $type || VariableType::Choices === $type;

        if ($chooses === ([] === $choices)) {
            throw InvalidLogic::because(\sprintf('The variable "%s" lists its choices only when it is a choice, and then at least one.', $name));
        }
    }
}
