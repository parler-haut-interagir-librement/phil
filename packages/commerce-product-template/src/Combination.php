<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;

/**
 * One value of each axis of a template, in the template's order of axes: what one product of a design is.
 */
final readonly class Combination
{
    /**
     * @param non-empty-array<string, AxisValue> $values by axis code, in the template's order
     */
    public function __construct(
        public array $values,
    ) {
    }

    public function value(string $axis): ?AxisValue
    {
        return $this->values[$axis] ?? null;
    }

    /**
     * @return non-empty-array<string, string> the code of each value, by axis code
     */
    public function codes(): array
    {
        return array_map(static fn (AxisValue $value): string => $value->code, $this->values);
    }

    /**
     * Whether, for every axis the condition names, the combination's value is one it lists; an axis the combination
     * does not have never matches.
     *
     * @param array<string, list<string>> $condition value codes by axis code
     */
    public function matches(array $condition): bool
    {
        return array_all($condition, fn (array $codes, string $axis): bool => isset($this->values[$axis]) && \in_array($this->values[$axis]->code, $codes, true));
    }

    /**
     * The combination as a person reads it in a message: `size M, colour BK`.
     */
    public function describe(): string
    {
        return implode(', ', array_map(static fn (string $axis, AxisValue $value): string => $axis.' '.$value->code, array_keys($this->values), $this->values));
    }

    /**
     * A condition of value codes by axis, checked and with each code once: what an exclusion, an artwork or an option
     * names.
     *
     * @param array<array-key, mixed> $condition
     *
     * @return array<string, list<string>>
     *
     * @throws InvalidDefinition for anything but lists of value codes by axis code
     */
    public static function condition(array $condition, string $what): array
    {
        $checked = [];

        foreach ($condition as $axis => $codes) {
            if (!\is_string($axis) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $axis) || !\is_array($codes) || !array_is_list($codes) || [] === $codes) {
                throw InvalidDefinition::because(\sprintf('%s names, by axis code, a list of one value code or more.', $what));
            }

            $values = [];

            foreach ($codes as $code) {
                if (!\is_string($code) || 1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,15}$/D', $code)) {
                    throw InvalidDefinition::because(\sprintf('%s names value codes of 1 to 16 letters, digits, ".", "_" or "-".', $what));
                }

                $values[] = $code;
            }

            $checked[$axis] = array_values(array_unique($values));
        }

        return $checked;
    }
}
