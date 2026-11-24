<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * The values of a row as the drivers give them, narrowed to what the columns hold: a driver gives an integer column
 * as an integer or as its digits, a moment as text in the format of its platform.
 */
final readonly class Columns
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function text(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    public static function string(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    public function moment(mixed $value): ?DateTimeImmutable
    {
        $moment = null === $value ? null : Type::getType(Types::DATETIME_UTC_IMMUTABLE)->convertToPHPValue($value, $this->connection->getDatabasePlatform());

        return $moment instanceof DateTimeImmutable ? $moment : null;
    }

    public function bool(mixed $value): bool
    {
        return true === Type::getType(Types::BOOLEAN)->convertToPHPValue($value, $this->connection->getDatabasePlatform());
    }

    /**
     * @return array<array-key, mixed>
     */
    public function json(mixed $value): array
    {
        $decoded = null === $value ? null : Type::getType(Types::JSON)->convertToPHPValue($value, $this->connection->getDatabasePlatform());

        return \is_array($decoded) ? $decoded : [];
    }
}
