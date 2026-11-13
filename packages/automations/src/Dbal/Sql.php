<?php

declare(strict_types=1);

namespace Phil\Automations\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Phil\Automations\Exception\InvalidDefinition;
use UnexpectedValueException;

/**
 * What the two DBAL stores share: the insert that leaves a row of the same key alone, in each server's dialect, the
 * check of a table name, and the reading of a column.
 */
final class Sql
{
    /**
     * An insert that does nothing when a row of the same key is there, without aborting a transaction.
     *
     * @param list<string> $columns every column inserted
     * @param list<string> $key     the columns of the primary key
     */
    public static function insertUnlessPresent(AbstractPlatform $platform, string $table, array $columns, array $key): string
    {
        $into = \sprintf('INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', array_fill(0, \count($columns), '?')));

        return match (true) {
            $platform instanceof PostgreSQLPlatform => \sprintf('INSERT %s ON CONFLICT (%s) DO NOTHING', $into, implode(', ', $key)),
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE '.$into,
            $platform instanceof SQLitePlatform => 'INSERT OR IGNORE '.$into,
            default => throw InvalidDefinition::because(\sprintf('The DBAL stores of automations run on PostgreSQL, MySQL, MariaDB and SQLite; not on %s.', $platform::class)),
        };
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidDefinition when the name is not a lowercase SQL name of at most 63 characters
     */
    public static function tableName(string $name): string
    {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $name)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $name));
        }

        return $name;
    }

    public static function integer(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new UnexpectedValueException('A numeric column of automations holds something else.');
        }

        return (int) $value;
    }

    public static function string(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException('A text column of automations holds something else.');
        }

        return $value;
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }
}
