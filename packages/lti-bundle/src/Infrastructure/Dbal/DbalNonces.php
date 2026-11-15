<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Phil\Lti\Contract\Nonces;
use Psr\Clock\ClockInterface;

/**
 * The nonces used once, in a table: a value is kept by its SHA-256 under its scope, and the primary key makes the
 * check and the write one step. A row that expired is replaced in a second step, which the primary key keeps atomic too.
 */
final readonly class DbalNonces implements Nonces
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function use(string $scope, string $value, DateTimeImmutable $expiresAt): bool
    {
        $row = ['scope' => $scope, 'value_hash' => hash('sha256', $value), 'expires_at' => $expiresAt->getTimestamp()];

        try {
            $this->connection->insert($this->tables->nonces, $row);

            return true;
        } catch (UniqueConstraintViolationException) {
            $removed = $this->connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE scope = ? AND value_hash = ? AND expires_at <= ?', $this->tables->nonces),
                [$row['scope'], $row['value_hash'], $this->clock->now()->getTimestamp()],
            );

            if (1 !== $removed) {
                return false;
            }

            try {
                $this->connection->insert($this->tables->nonces, $row);
            } catch (UniqueConstraintViolationException) {
                return false;
            }

            return true;
        }
    }

    /**
     * Removes the nonces that expired; the count removed.
     */
    public function purge(): int
    {
        return (int) $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE expires_at <= ?', $this->tables->nonces), [$this->clock->now()->getTimestamp()]);
    }
}
