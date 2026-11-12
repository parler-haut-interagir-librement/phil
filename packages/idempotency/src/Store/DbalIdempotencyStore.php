<?php

declare(strict_types=1);

namespace Phil\Idempotency\Store;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use Psr\Clock\ClockInterface;

/**
 * The keys in the table of {@see IdempotencyTable}, through a Doctrine DBAL connection.
 *
 * A reservation deletes the key's expired or failed row, then inserts the key unless it is there, in one statement
 * that never touches a live row and does not abort the transaction: `ON CONFLICT DO NOTHING` on PostgreSQL,
 * `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite; elsewhere, a plain insert whose unique
 * violation is a `false`. The primary key on scope and key makes the insert the atomic step. An expired row is
 * never read.
 */
final readonly class DbalIdempotencyStore implements IdempotencyStore
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function find(IdempotencyScope $scope, IdempotencyKey $key): ?IdempotencyRecord
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT fingerprint, state, status, headers, body, expires_at FROM %s WHERE scope = ? AND idempotency_key = ? AND expires_at > ?', IdempotencyTable::NAME),
            [$scope->value, $key->value, $this->now()],
            [Types::STRING, Types::STRING, Types::DATETIME_IMMUTABLE],
        );

        if (false === $row) {
            return null;
        }

        $state = IdempotencyState::from($this->text($row['state']));
        $response = null;

        if (IdempotencyState::Completed === $state && is_numeric($row['status'])) {
            /** @var array<string, list<string>> $headers written by complete() */
            $headers = json_decode($this->text($row['headers']), true, flags: \JSON_THROW_ON_ERROR);
            $response = StoredResponse::of((int) $row['status'], $headers, $this->text($row['body']));
        }

        // Written in UTC without a zone: read back in UTC, whatever the default zone of PHP.
        $expiresAt = new DateTimeImmutable($this->text($row['expires_at']), new DateTimeZone('UTC'));

        return IdempotencyRecord::of(PayloadFingerprint::fromHex($this->text($row['fingerprint'])), $state, $expiresAt, $response);
    }

    #[Override]
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): bool
    {
        // An expired or failed row takes no place: once it is gone, the insert decides alone.
        $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE scope = ? AND idempotency_key = ? AND (expires_at <= ? OR state = ?)', IdempotencyTable::NAME),
            [$scope->value, $key->value, $this->now(), IdempotencyState::Failed->value],
            [Types::STRING, Types::STRING, Types::DATETIME_IMMUTABLE, Types::STRING],
        );

        try {
            return 0 < $this->connection->executeStatement(
                self::reservation($this->connection->getDatabasePlatform()),
                [$scope->value, $key->value, $fingerprint->hex, IdempotencyState::InProgress->value, $this->utc($expiresAt)],
                [Types::STRING, Types::STRING, Types::STRING, Types::STRING, Types::DATETIME_IMMUTABLE],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    #[Override]
    public function complete(IdempotencyScope $scope, IdempotencyKey $key, StoredResponse $response, DateTimeImmutable $expiresAt): void
    {
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET state = ?, status = ?, headers = ?, body = ?, expires_at = ? WHERE scope = ? AND idempotency_key = ?', IdempotencyTable::NAME),
            [IdempotencyState::Completed->value, $response->status, $response->headers, $response->body, $this->utc($expiresAt), $scope->value, $key->value],
            [Types::STRING, Types::SMALLINT, Types::JSON, Types::BLOB, Types::DATETIME_IMMUTABLE, Types::STRING, Types::STRING],
        );
    }

    #[Override]
    public function fail(IdempotencyScope $scope, IdempotencyKey $key, DateTimeImmutable $expiresAt): void
    {
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET state = ?, status = NULL, headers = NULL, body = NULL, expires_at = ? WHERE scope = ? AND idempotency_key = ?', IdempotencyTable::NAME),
            [IdempotencyState::Failed->value, $this->utc($expiresAt), $scope->value, $key->value],
            [Types::STRING, Types::DATETIME_IMMUTABLE, Types::STRING, Types::STRING],
        );
    }

    #[Override]
    public function release(IdempotencyScope $scope, IdempotencyKey $key): void
    {
        $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE scope = ? AND idempotency_key = ?', IdempotencyTable::NAME),
            [$scope->value, $key->value],
        );
    }

    /**
     * Forgets the expired keys; run it from a scheduled task.
     *
     * @return int how many were forgotten
     */
    public function purge(): int
    {
        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE expires_at <= ?', IdempotencyTable::NAME),
            [$this->now()],
            [Types::DATETIME_IMMUTABLE],
        );
    }

    /**
     * The insert of a reservation on this platform, which leaves a live row alone without an error where it can.
     */
    public static function reservation(AbstractPlatform $platform): string
    {
        $into = \sprintf('INTO %s (scope, idempotency_key, fingerprint, state, expires_at) VALUES (?, ?, ?, ?, ?)', IdempotencyTable::NAME);

        return match (true) {
            $platform instanceof PostgreSQLPlatform => 'INSERT '.$into.' ON CONFLICT (scope, idempotency_key) DO NOTHING',
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE '.$into,
            $platform instanceof SQLitePlatform => 'INSERT OR IGNORE '.$into,
            default => 'INSERT '.$into,
        };
    }

    /**
     * A column read as text: a string, or a stream for a binary column on some drivers.
     */
    private function text(mixed $value): string
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return \is_string($value) ? $value : '';
    }

    private function now(): DateTimeImmutable
    {
        return $this->utc($this->clock->now());
    }

    private function utc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone('UTC'));
    }
}
