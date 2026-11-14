<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The instants of the tables of the bundle, kept in `datetime_utc_immutable` columns: written in UTC in the connection
 * platform's own format, and read back in that format only. Unlike the DBAL type, nothing else is guessed: a column
 * that holds any other text, such as "yesterday", is refused rather than read as some date.
 */
final class UtcMoments
{
    private function __construct()
    {
    }

    public static function text(Connection $connection, DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format($connection->getDatabasePlatform()->getDateTimeFormatString());
    }

    public static function optionalText(Connection $connection, ?DateTimeImmutable $moment): ?string
    {
        return $moment instanceof DateTimeImmutable ? self::text($connection, $moment) : null;
    }

    /**
     * @throws InvalidPrivacyValue when the column is not a date and time in the platform's format
     */
    public static function read(Connection $connection, mixed $value, string $column): DateTimeImmutable
    {
        $moment = \is_string($value)
            ? DateTimeImmutable::createFromFormat('!'.$connection->getDatabasePlatform()->getDateTimeFormatString(), $value, new DateTimeZone('UTC'))
            : false;

        if (false === $moment) {
            throw InvalidPrivacyValue::because(\sprintf('The column "%s" holds no date and time in the format of its database.', $column));
        }

        return $moment;
    }

    /**
     * @throws InvalidPrivacyValue when the column is neither null nor a date and time in the platform's format
     */
    public static function readOptional(Connection $connection, mixed $value, string $column): ?DateTimeImmutable
    {
        return null === $value ? null : self::read($connection, $value, $column);
    }
}
