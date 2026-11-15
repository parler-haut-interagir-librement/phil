<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use LogicException;
use UnexpectedValueException;

/**
 * What the DBAL repositories share: the insert that leaves a row of the same key alone, in each server's dialect, and
 * the reading of columns.
 */
final class Sql
{
    /**
     * Inserts a row unless one of the same key is there, without aborting a transaction.
     *
     * @param array<string, int|string|null> $row
     * @param list<string>                   $key the columns of the primary key or of a unique index
     *
     * @return bool whether the row was inserted
     */
    public static function insertUnlessPresent(Connection $connection, string $table, array $row, array $key): bool
    {
        $platform = $connection->getDatabasePlatform();
        $into = \sprintf('INTO %s (%s) VALUES (%s)', $table, implode(', ', array_keys($row)), implode(', ', array_fill(0, \count($row), '?')));
        $sql = match (true) {
            $platform instanceof PostgreSQLPlatform => \sprintf('INSERT %s ON CONFLICT (%s) DO NOTHING', $into, implode(', ', $key)),
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE '.$into,
            $platform instanceof SQLitePlatform => 'INSERT OR IGNORE '.$into,
            default => throw new LogicException(\sprintf('The newsletter runs on PostgreSQL, MySQL, MariaDB and SQLite; not on %s.', $platform::class)),
        };

        return 1 === (int) $connection->executeStatement($sql, array_values($row));
    }

    /**
     * Inserts a row, or updates the row of the same key.
     *
     * @param array<string, int|string|null> $row
     * @param list<string>                   $key
     */
    public static function upsert(Connection $connection, string $table, array $row, array $key): void
    {
        if (self::insertUnlessPresent($connection, $table, $row, $key)) {
            return;
        }

        $criteria = array_intersect_key($row, array_flip($key));
        $connection->update($table, array_diff_key($row, $criteria), $criteria);
    }

    public static function integer(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new UnexpectedValueException('A numeric column of the newsletter holds something else.');
        }

        return (int) $value;
    }

    public static function string(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException('A text column of the newsletter holds something else.');
        }

        return $value;
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    public static function instant(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.self::integer($value));
    }

    public static function nullableInstant(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : self::instant($value);
    }
}
