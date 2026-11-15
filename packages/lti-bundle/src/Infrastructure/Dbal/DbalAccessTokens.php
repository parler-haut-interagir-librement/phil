<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\Lti\Contract\AccessTokens;
use Phil\Lti\Platform\Token\AccessToken;
use Psr\Clock\ClockInterface;

/**
 * The access tokens the platform granted, in a table, by the hash of their value only.
 */
final readonly class DbalAccessTokens implements AccessTokens
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function save(AccessToken $token): void
    {
        $this->connection->insert($this->tables->accessTokens, ['token_hash' => $token->hash, 'client_id' => $token->clientId, 'scopes' => implode(' ', $token->scopes), 'expires_at' => $token->expiresAt->getTimestamp()]);
    }

    #[Override]
    public function find(string $hash): ?AccessToken
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT client_id, scopes, expires_at FROM %s WHERE token_hash = ? AND expires_at > ?', $this->tables->accessTokens), [$hash, $this->clock->now()->getTimestamp()]);

        if (false === $row) {
            return null;
        }

        $scopes = Row::string($row, 'scopes');

        return new AccessToken($hash, Row::string($row, 'client_id'), '' === $scopes ? [] : explode(' ', $scopes), new DateTimeImmutable('@'.Row::int($row, 'expires_at')));
    }

    public function purge(): int
    {
        return (int) $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE expires_at <= ?', $this->tables->accessTokens), [$this->clock->now()->getTimestamp()]);
    }
}
