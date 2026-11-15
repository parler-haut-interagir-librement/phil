<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use UnexpectedValueException;

/**
 * Typed reads of a row, whatever type the driver gives: an integer may come as text, a float as text.
 *
 * @internal
 */
final class Row
{
    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        return \is_string($value) ? $value : throw new UnexpectedValueException(\sprintf('The column "%s" is not text.', $column));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function optionalString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return null === $value ? null : self::string($row, $column);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $column): int
    {
        $value = $row[$column] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d{1,19}$/D', $value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(\sprintf('The column "%s" is not an integer.', $column));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function optionalInt(array $row, string $column): ?int
    {
        return null === ($row[$column] ?? null) ? null : self::int($row, $column);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function float(array $row, string $column): float
    {
        $value = $row[$column] ?? null;

        if (\is_int($value) || \is_float($value) || (\is_string($value) && is_numeric($value))) {
            return (float) $value;
        }

        throw new UnexpectedValueException(\sprintf('The column "%s" is not a number.', $column));
    }
}
