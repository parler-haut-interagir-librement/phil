<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use JsonException;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;

/**
 * The statements the repositories share: an insert that leaves a row whose key is taken without failing — and so
 * without aborting the transaction it runs in on PostgreSQL —, and JSON.
 *
 * @internal
 */
final class Sql
{
    private function __construct()
    {
    }

    /**
     * Inserts the row unless its key is taken.
     *
     * @param array<string, mixed> $row
     *
     * @return bool whether the row was inserted
     */
    public static function insertIgnoring(Connection $connection, string $table, array $row): bool
    {
        $platform = $connection->getDatabasePlatform();
        $columns = implode(', ', array_keys($row));
        $values = implode(', ', array_fill(0, \count($row), '?'));
        $parameters = array_values($row);

        if ($platform instanceof SQLitePlatform) {
            return 1 === (int) $connection->executeStatement(\sprintf('INSERT OR IGNORE INTO %s (%s) VALUES (%s)', $table, $columns, $values), $parameters);
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return 1 === (int) $connection->executeStatement(\sprintf('INSERT IGNORE INTO %s (%s) VALUES (%s)', $table, $columns, $values), $parameters);
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return 1 === (int) $connection->executeStatement(\sprintf('INSERT INTO %s (%s) VALUES (%s) ON CONFLICT DO NOTHING', $table, $columns, $values), $parameters);
        }

        try {
            $connection->insert($table, $row);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @throws JsonException never for the answers and definitions the repositories write
     */
    public static function json(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidQuestionnaire when the column does not hold a JSON object
     */
    public static function decode(mixed $json): array
    {
        try {
            $value = \is_string($json) ? json_decode($json, true, 128, \JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $value = null;
        }

        if (!\is_array($value)) {
            throw InvalidQuestionnaire::because('A stored column holds a JSON object.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
