<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\Lti\Service\Ags\Score;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\PendingScore;
use Phil\Url\Url;

/**
 * The scores the tool still has to send, in a table.
 */
final readonly class DbalPendingScores implements PendingScores
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
        private bool $allowLocalhost = false,
    ) {
    }

    #[Override]
    public function save(PendingScore $score): void
    {
        $this->connection->insert($this->tables->pendingScores, [
            'id' => $score->id,
            'issuer' => $score->issuer,
            'client_id' => $score->clientId,
            'line_item' => $score->lineItem->toString(),
            'payload' => json_encode($score->score->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'created_at' => $score->createdAt->getTimestamp(),
        ]);
    }

    #[Override]
    public function find(string $id): ?PendingScore
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s WHERE id = ?', $this->tables->pendingScores), [$id]);

        if (false === $row) {
            return null;
        }

        $payload = json_decode(Row::string($row, 'payload'), true, 8, \JSON_THROW_ON_ERROR);

        return new PendingScore($id, Row::string($row, 'issuer'), Row::string($row, 'client_id'), Url::fromString(Row::string($row, 'line_item'), $this->allowLocalhost), Score::fromArray(\is_array($payload) ? $payload : []), new DateTimeImmutable('@'.Row::int($row, 'created_at')));
    }

    #[Override]
    public function delete(string $id): void
    {
        $this->connection->delete($this->tables->pendingScores, ['id' => $id]);
    }
}
