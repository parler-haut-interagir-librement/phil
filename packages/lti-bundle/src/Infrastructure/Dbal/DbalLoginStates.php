<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\Lti\Contract\LoginStates;
use Phil\Lti\Security\LoginState;
use Psr\Clock\ClockInterface;

/**
 * The login states of the tool, in a table, kept by the SHA-256 of the state. A state is taken by deleting its row:
 * of two launches with the same state, only the one whose delete removed the row goes on.
 */
final readonly class DbalLoginStates implements LoginStates
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function save(LoginState $state): void
    {
        $this->connection->insert($this->tables->loginStates, [
            'state_hash' => hash('sha256', $state->state),
            'nonce' => $state->nonce,
            'issuer' => $state->issuer,
            'client_id' => $state->clientId,
            'target' => $state->targetLinkUri,
            'expires_at' => $state->expiresAt->getTimestamp(),
        ]);
    }

    #[Override]
    public function take(string $state): ?LoginState
    {
        $hash = hash('sha256', $state);
        $row = $this->connection->fetchAssociative(\sprintf('SELECT nonce, issuer, client_id, target, expires_at FROM %s WHERE state_hash = ?', $this->tables->loginStates), [$hash]);

        if (false === $row || 1 !== $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE state_hash = ?', $this->tables->loginStates), [$hash])) {
            return null;
        }

        $expiresAt = Row::int($row, 'expires_at');

        if ($expiresAt <= $this->clock->now()->getTimestamp()) {
            return null;
        }

        return new LoginState($state, Row::string($row, 'nonce'), Row::string($row, 'issuer'), Row::string($row, 'client_id'), Row::string($row, 'target'), new DateTimeImmutable('@'.$expiresAt));
    }

    public function purge(): int
    {
        return (int) $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE expires_at <= ?', $this->tables->loginStates), [$this->clock->now()->getTimestamp()]);
    }
}
