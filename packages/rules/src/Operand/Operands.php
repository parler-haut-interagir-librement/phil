<?php

declare(strict_types=1);

namespace Phil\Rules\Operand;

use Phil\Rules\Exception\MissingOperand;

/**
 * The operands of a condition, checked against its type's schema: texts, dates, identifiers and choices are strings,
 * numbers are floats, lists are lists. An evaluator reads them through the typed accessors.
 */
final readonly class Operands
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values)
    {
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    /**
     * A text, an identifier, a choice or a date.
     */
    public function string(string $name): string
    {
        $value = $this->values[$name] ?? null;

        return \is_string($value) ? $value : throw MissingOperand::of($name, 'a string');
    }

    public function int(string $name): int
    {
        $value = $this->values[$name] ?? null;

        return \is_int($value) ? $value : throw MissingOperand::of($name, 'an integer');
    }

    public function float(string $name): float
    {
        $value = $this->values[$name] ?? null;

        return \is_float($value) ? $value : throw MissingOperand::of($name, 'a number');
    }

    public function bool(string $name): bool
    {
        $value = $this->values[$name] ?? null;

        return \is_bool($value) ? $value : throw MissingOperand::of($name, 'a boolean');
    }

    public function scalar(string $name): string|int|float|bool
    {
        $value = $this->values[$name] ?? null;

        return \is_scalar($value) ? $value : throw MissingOperand::of($name, 'a scalar');
    }

    /**
     * An identifier list.
     *
     * @return list<string>
     */
    public function strings(string $name): array
    {
        $value = $this->values[$name] ?? null;

        if (!\is_array($value)) {
            throw MissingOperand::of($name, 'a list');
        }

        return array_values(array_filter($value, \is_string(...)));
    }

    /**
     * A scalar list.
     *
     * @return list<string|int|float|bool>
     */
    public function scalars(string $name): array
    {
        $value = $this->values[$name] ?? null;

        if (!\is_array($value)) {
            throw MissingOperand::of($name, 'a list');
        }

        return array_values(array_filter($value, \is_scalar(...)));
    }

    /**
     * The operands as a rule writes them.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
