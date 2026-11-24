<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;

/**
 * A row as the database gives it, read column by column into the types the domain expects: a column of another type
 * means the table is not the bundle's, and is refused rather than guessed.
 */
final readonly class Row
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        private array $values,
        private Connection $connection,
    ) {
    }

    /**
     * The values of one column, as the database gives them, read as identifiers: strings, and integers a driver may
     * give for a column of digits.
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    public static function strings(array $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (\is_string($value) || \is_int($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings;
    }

    /**
     * @throws InvalidFollow when the column is not a string
     */
    public function string(string $column): string
    {
        $value = $this->values[$column] ?? null;

        return \is_string($value) ? $value : throw InvalidFollow::because(\sprintf('The column "%s" holds a string.', $column));
    }

    /**
     * @throws InvalidFollow when the column is neither null nor a string
     */
    public function nullableString(string $column): ?string
    {
        return null === ($this->values[$column] ?? null) ? null : $this->string($column);
    }

    /**
     * @throws InvalidFollow when the column is not a boolean as the platform stores one
     */
    public function bool(string $column): bool
    {
        $value = $this->connection->convertToPHPValue($this->values[$column] ?? null, Types::BOOLEAN);

        return \is_bool($value) ? $value : throw InvalidFollow::because(\sprintf('The column "%s" holds a boolean.', $column));
    }

    /**
     * @throws InvalidFollow when the column is not an instant
     */
    public function instant(string $column): DateTimeImmutable
    {
        $value = $this->connection->convertToPHPValue($this->string($column), Types::DATETIME_UTC_IMMUTABLE);

        return $value instanceof DateTimeImmutable ? $value : throw InvalidFollow::because(\sprintf('The column "%s" holds an instant.', $column));
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidFollow when the column is not a JSON object or list
     */
    public function json(string $column): array
    {
        $value = json_decode($this->string($column), true);

        return \is_array($value) ? $value : throw InvalidFollow::because(\sprintf('The column "%s" holds a JSON object or list.', $column));
    }
}
