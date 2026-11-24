<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FaqBundle\Domain\Contract\SpentVoteTokenRepository;
use Phil\FaqBundle\Domain\Model\SpentVoteToken;

/**
 * The spent vote tokens in their table: the primary key refuses the second use of a token for a question, whichever
 * comes first.
 */
final readonly class DbalSpentVoteTokens implements SpentVoteTokenRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function spend(string $id, DateTimeImmutable $expiresAt): bool
    {
        try {
            $this->connection->insert(SpentVoteToken::TABLE, ['id' => $id, 'expires_at' => $expiresAt], ['expires_at' => Types::DATETIME_UTC_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE expires_at < ?', SpentVoteToken::TABLE),
            [$now],
            [Types::DATETIME_UTC_IMMUTABLE],
        );
    }
}
