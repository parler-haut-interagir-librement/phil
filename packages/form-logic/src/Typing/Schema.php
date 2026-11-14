<?php

declare(strict_types=1);

namespace Phil\FormLogic\Typing;

use Phil\FormLogic\Exception\InvalidLogic;

/**
 * The variables a condition may read, by name.
 */
final readonly class Schema
{
    /** @var array<string, VariableDefinition> */
    public array $variables;

    /**
     * @param array<array-key, VariableDefinition> $variables
     *
     * @throws InvalidLogic when two variables have the same name
     */
    public function __construct(array $variables = [])
    {
        $byName = [];

        foreach ($variables as $variable) {
            if (isset($byName[$variable->name])) {
                throw InvalidLogic::because(\sprintf('The variable "%s" is defined twice.', $variable->name));
            }

            $byName[$variable->name] = $variable;
        }

        $this->variables = $byName;
    }

    /**
     * The same variables and this one.
     *
     * @throws InvalidLogic when a variable already has its name
     */
    public function with(VariableDefinition $variable): self
    {
        return new self([...$this->variables, $variable]);
    }

    public function get(string $name): ?VariableDefinition
    {
        return $this->variables[$name] ?? null;
    }
}
