<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * Reads and writes the values of a row the same way on every driver: moments in UTC, booleans whatever a driver
 * returns for them, numbers that come back as text, and the insert-or-update every store uses.
 */
final readonly class Rows
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Updates the row the key names, or inserts it when there is none.
     *
     * @param non-empty-string      $table
     * @param array<string, mixed>  $key
     * @param array<string, mixed>  $values
     * @param array<string, string> $types
     */
    public function put(string $table, array $key, array $values, array $types = []): void
    {
        $where = implode(' AND ', array_map(static fn (string $column): string => $column.' = ?', array_keys($key)));
        $exists = false !== $this->connection->fetchOne('SELECT 1 FROM '.$table.' WHERE '.$where, array_values($key));

        if ($exists) {
            $this->connection->update($table, $values, $key, $types);

            return;
        }

        $this->connection->insert($table, $key + $values, $types);
    }

    public function moment(mixed $value): ?DateTimeImmutable
    {
        // The type reads a null as null.
        $moment = Type::getType(Types::DATETIME_UTC_IMMUTABLE)->convertToPHPValue($value, $this->connection->getDatabasePlatform());

        return $moment instanceof DateTimeImmutable ? $moment : null;
    }

    public function requiredMoment(mixed $value): DateTimeImmutable
    {
        return $this->moment($value) ?? new DateTimeImmutable('@0');
    }

    public static function bool(mixed $value): bool
    {
        return true === $value || 1 === $value || '1' === $value || 't' === $value || 'true' === $value;
    }

    public static function nullableBool(mixed $value): ?bool
    {
        return null === $value ? null : self::bool($value);
    }

    public static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    public static function nullableText(mixed $value): ?string
    {
        return null === $value ? null : self::text($value);
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return null === $value ? null : self::int($value);
    }

    public static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
