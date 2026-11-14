<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Placement;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Where a tenant's data lives: in the shared database, beside the other shared tenants and kept apart by the
 * Doctrine filter, or in a database of its own.
 *
 * A dedicated placement names its database, never how to reach it: the name is a key the application maps to a
 * server and credentials, so that a placement can be logged, cached and carried in a message without disclosing
 * anything. A name is lowercase ASCII letters, digits and underscores, starts with a letter, and is at most
 * {@see self::MAX_DATABASE_LENGTH} bytes, the longest identifier PostgreSQL keeps: it is the same on every platform,
 * whatever the case sensitivity of its file system, and can never be read as SQL.
 */
final readonly class Placement
{
    public const int MAX_DATABASE_LENGTH = 63;

    private function __construct(
        public ?string $database,
    ) {
    }

    public static function shared(): self
    {
        return new self(null);
    }

    /**
     * @throws InvalidValue when the name is not a database name
     */
    public static function dedicated(string $database): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]*$/D', $database)) {
            throw InvalidValue::because(\sprintf('The database name "%s" is lowercase ASCII letters, digits and underscores, starting with a letter.', addcslashes($database, "\0..\37\177..\377")));
        }

        if (\strlen($database) > self::MAX_DATABASE_LENGTH) {
            throw InvalidValue::because(\sprintf('A database name is at most %d bytes; this one is %d.', self::MAX_DATABASE_LENGTH, \strlen($database)));
        }

        return new self($database);
    }

    public function isShared(): bool
    {
        return null === $this->database;
    }

    public function isDedicated(): bool
    {
        return null !== $this->database;
    }

    /**
     * Two placements are equal when they put data in the same database.
     */
    public function equals(self $other): bool
    {
        return $this->database === $other->database;
    }

    /**
     * `shared`, or the name of the dedicated database: for a log line or a console table.
     */
    public function toString(): string
    {
        return $this->database ?? 'shared';
    }
}
