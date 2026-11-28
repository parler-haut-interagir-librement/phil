<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Phil\Shifts\Exception\InvalidDocument;

/**
 * The JSON of the stored shifts, places and offers, and the values the drivers give.
 *
 * @internal
 */
final class Json
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    public static function encode(array $fields): string
    {
        return json_encode($fields, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws InvalidDocument when the stored text is not JSON
     */
    public static function decode(mixed $text): mixed
    {
        try {
            return json_decode(self::string($text), true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidDocument::because('A stored column is not JSON.', $jsonException);
        }
    }

    public static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw InvalidDocument::because('A stored column holds a text.');
    }

    /**
     * The instant a `datetime_utc_immutable` column holds, or null.
     *
     * @throws InvalidDocument when the column holds something else
     */
    public static function instant(Connection $connection, mixed $value): ?DateTimeImmutable
    {
        try {
            $instant = $connection->convertToPHPValue($value, Types::DATETIME_UTC_IMMUTABLE);
        } catch (ConversionException $conversionException) {
            throw InvalidDocument::because('A stored column holds an instant.', $conversionException);
        }

        // The conversion of the type gives a date or null, and throws for anything else.
        return $instant instanceof DateTimeImmutable ? $instant : null;
    }

    /**
     * The instant a `datetime_utc_immutable` column that is not null holds.
     *
     * @throws InvalidDocument when the column holds something else, or nothing
     */
    public static function requiredInstant(Connection $connection, mixed $value): DateTimeImmutable
    {
        return self::instant($connection, $value) ?? throw InvalidDocument::because('A stored column holds an instant.');
    }

    public static function int(mixed $value): int
    {
        return match (true) {
            \is_int($value) => $value,
            \is_string($value) && 1 === preg_match('/^-?\d{1,18}$/D', $value) => (int) $value,
            default => throw InvalidDocument::because('A stored column holds a whole number.'),
        };
    }
}
