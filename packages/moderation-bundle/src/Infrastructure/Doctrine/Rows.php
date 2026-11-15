<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * Reads the columns of a row as the stores need them, whatever the driver gives: a string, a number, a moment in UTC.
 */
final readonly class Rows
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function text(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function required(array $row, string $column): string
    {
        return $this->text($row, $column) ?? '';
    }

    /**
     * @param array<string, mixed> $row
     */
    public function number(array $row, string $column): int
    {
        $value = $row[$column] ?? 0;

        return \is_int($value) ? $value : (int) (\is_scalar($value) ? $value : 0);
    }

    /**
     * A moment of a column the table keeps not null, read in UTC.
     *
     * @param array<string, mixed> $row
     */
    public function moment(array $row, string $column): DateTimeImmutable
    {
        $moment = $this->connection->convertToPHPValue($row[$column] ?? null, Types::DATETIME_UTC_IMMUTABLE);
        \assert($moment instanceof DateTimeImmutable);

        return $moment;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function flag(array $row, string $column): bool
    {
        $value = $this->connection->convertToPHPValue($row[$column] ?? null, Types::BOOLEAN);

        return true === $value;
    }
}
