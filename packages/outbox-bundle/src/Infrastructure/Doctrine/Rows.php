<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Doctrine;

use Phil\Outbox\Exception\InvalidRecord;

/**
 * The columns of a row as PHP types: drivers give integers as integers or as digit strings.
 */
final class Rows
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidRecord when the value is not an integer
     */
    public static function int(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d{1,18}$/D', $value)) {
            return (int) $value;
        }

        throw InvalidRecord::because('A column of the outbox table holds no integer.');
    }

    /**
     * @throws InvalidRecord when the value is not text
     */
    public static function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw InvalidRecord::because('A column of the outbox table holds no text.');
        }

        return $value;
    }

    /**
     * @throws InvalidRecord when the value is neither text nor null
     */
    public static function nullableText(mixed $value): ?string
    {
        return null === $value ? null : self::text($value);
    }
}
