<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Types;
use JsonException;

/**
 * The statements the repositories share: many rows in one `INSERT`, one that skips the rows whose key is taken
 * without failing the statement — and so without aborting the transaction a PostgreSQL worker may be in —, and JSON.
 */
final class Sql
{
    private function __construct()
    {
    }

    /**
     * Inserts the rows in one statement.
     *
     * @param list<string>       $columns
     * @param list<list<mixed>>  $rows
     * @param array<int, string> $types   the DBAL type of each column, by position, when not a string
     */
    public static function insert(Connection $connection, string $table, array $columns, array $rows, array $types = []): void
    {
        if ([] !== $rows) {
            self::execute($connection, 'INSERT INTO '.$table, '', $columns, $rows, $types);
        }
    }

    /**
     * Inserts the rows whose key is free and leaves the others, in one statement on SQLite, MySQL, MariaDB and
     * PostgreSQL, row by row elsewhere.
     *
     * @param list<string>       $columns
     * @param list<list<mixed>>  $rows
     * @param array<int, string> $types   the DBAL type of each column, by position, when not a string
     */
    public static function insertIgnoring(Connection $connection, string $table, array $columns, array $rows, array $types = []): void
    {
        if ([] === $rows) {
            return;
        }

        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::execute($connection, 'INSERT OR IGNORE INTO '.$table, '', $columns, $rows, $types);
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            self::execute($connection, 'INSERT IGNORE INTO '.$table, '', $columns, $rows, $types);
        } elseif ($platform instanceof PostgreSQLPlatform) {
            self::execute($connection, 'INSERT INTO '.$table, ' ON CONFLICT DO NOTHING', $columns, $rows, $types);
        } else {
            foreach ($rows as $row) {
                try {
                    self::execute($connection, 'INSERT INTO '.$table, '', $columns, [$row], $types);
                } catch (UniqueConstraintViolationException) {
                    // The key is taken: the row is left, as the other platforms leave it.
                }
            }
        }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @throws JsonException never for the arrays of strings the repositories write
     */
    public static function json(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<string>       $columns
     * @param list<list<mixed>>  $rows
     * @param array<int, string> $types
     */
    private static function execute(Connection $connection, string $into, string $suffix, array $columns, array $rows, array $types): void
    {
        $placeholders = '('.implode(', ', array_fill(0, \count($columns), '?')).')';
        $parameters = [];
        $parameterTypes = [];

        foreach ($rows as $row) {
            foreach ($row as $position => $value) {
                $parameters[] = $value;
                $parameterTypes[] = $types[$position] ?? Types::STRING;
            }
        }

        $connection->executeStatement(
            \sprintf('%s (%s) VALUES %s%s', $into, implode(', ', $columns), implode(', ', array_fill(0, \count($rows), $placeholders)), $suffix),
            $parameters,
            $parameterTypes,
        );
    }
}
