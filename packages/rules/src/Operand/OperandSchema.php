<?php

declare(strict_types=1);

namespace Phil\Rules\Operand;

use Phil\Rules\Exception\InvalidConditionType;
use Phil\Rules\Exception\InvalidRule;

/**
 * The operands a condition type accepts. A rule gives each required operand, may give the optional ones, and gives no
 * other: an unknown operand is refused rather than ignored, so that a misspelt name never weakens a condition.
 */
final readonly class OperandSchema
{
    /** @var array<string, Operand> */
    private array $operands;

    public function __construct(Operand ...$operands)
    {
        $byName = [];

        foreach ($operands as $operand) {
            if (isset($byName[$operand->name])) {
                throw InvalidConditionType::duplicateOperand($operand->name);
            }

            $byName[$operand->name] = $operand;
        }

        $this->operands = $byName;
    }

    /**
     * The operands written in a rule, checked and kept as their kinds say.
     *
     * @param array<array-key, mixed> $written
     *
     * @throws InvalidRule when an operand is unknown, missing or refused by its kind
     */
    public function read(array $written, string $type, string $path): Operands
    {
        if ([] !== $written && array_is_list($written)) {
            throw InvalidRule::malformed($path.'/operands', 'the operands are an object keyed by name, not a list');
        }

        foreach (array_keys($written) as $name) {
            if (!isset($this->operands[$name])) {
                throw InvalidRule::operand($path, $type, (string) $name, 'is not one the type accepts');
            }
        }

        $values = [];

        foreach ($this->operands as $name => $operand) {
            if (!\array_key_exists($name, $written)) {
                if ($operand->required) {
                    throw InvalidRule::operand($path, $type, $name, 'is required');
                }

                continue;
            }

            [$accepted, $value] = $operand->accept($written[$name]);

            if (!$accepted) {
                throw InvalidRule::operand($path, $type, $name, \is_string($value) ? $value : 'is refused');
            }

            $values[$name] = $value;
        }

        return new Operands($values);
    }

    /**
     * What a rule builder needs to offer the operands, in their order of declaration.
     *
     * @return list<array{name: string, kind: string, required: bool, choices?: list<string>, maxLength?: int, maxItems?: int, min?: int, max?: int}>
     */
    public function describe(): array
    {
        return array_values(array_map(static fn (Operand $operand): array => $operand->describe(), $this->operands));
    }
}
