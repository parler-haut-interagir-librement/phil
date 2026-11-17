<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use Override;
use Phil\Lti\Service\Ags\Score;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\PendingScore;
use Phil\Url\Url;

/**
 * The scores the tool still has to send, in a table. A claim is a guarded update per row — taken only while the row is
 * neither dispatched nor refused and its last claim, or its creation, is still at the threshold or before —, so that
 * of two recoveries at once, each row goes to one.
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
            'dispatched_at' => $score->dispatchedAt?->getTimestamp(),
            'refused_at' => $score->refusedAt?->getTimestamp(),
            'refusal' => null === $score->refusal ? null : PendingScore::refusal($score->refusal),
        ]);
    }

    #[Override]
    public function find(string $id): ?PendingScore
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s WHERE id = ?', $this->tables->pendingScores), [$id]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function markDispatched(string $id, DateTimeImmutable $at): void
    {
        $this->connection->update($this->tables->pendingScores, ['dispatched_at' => $at->getTimestamp()], ['id' => $id]);
    }

    #[Override]
    public function markRefused(string $id, string $reason, DateTimeImmutable $at): void
    {
        $this->connection->update($this->tables->pendingScores, ['refused_at' => $at->getTimestamp(), 'refusal' => PendingScore::refusal($reason)], ['id' => $id]);
    }

    #[Override]
    public function claim(DateTimeImmutable $before, DateTimeImmutable $now, int $limit): array
    {
        if ($now <= $before) {
            throw new InvalidArgumentException('A claim is made after its threshold: a row claimed now would be claimed again at once.');
        }

        $condition = 'dispatched_at IS NULL AND refused_at IS NULL AND COALESCE(claimed_at, created_at) <= ?';
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from($this->tables->pendingScores)
            ->where($condition)
            ->setParameter(0, $before->getTimestamp(), ParameterType::INTEGER)
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();
        $claimed = [];

        foreach ($ids as $id) {
            $id = Row::string(['id' => $id], 'id');
            $taken = $this->connection->executeStatement(
                \sprintf('UPDATE %s SET claimed_at = ? WHERE id = ? AND %s', $this->tables->pendingScores, $condition),
                [$now->getTimestamp(), $id, $before->getTimestamp()],
                [ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER],
            );
            $score = 1 === $taken ? $this->find($id) : null;

            if ($score instanceof PendingScore) {
                $claimed[] = $score;
            }
        }

        return $claimed;
    }

    #[Override]
    public function refused(DateTimeImmutable $before, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tables->pendingScores)
            ->where('refused_at IS NOT NULL AND refused_at <= :before')
            ->setParameter('before', $before->getTimestamp(), ParameterType::INTEGER)
            ->orderBy('refused_at')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->hydrate(...), $rows);
    }

    #[Override]
    public function delete(string $id): void
    {
        $this->connection->delete($this->tables->pendingScores, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PendingScore
    {
        $payload = json_decode(Row::string($row, 'payload'), true, 8, \JSON_THROW_ON_ERROR);
        $dispatched = Row::optionalInt($row, 'dispatched_at');
        $refused = Row::optionalInt($row, 'refused_at');

        return new PendingScore(
            Row::string($row, 'id'),
            Row::string($row, 'issuer'),
            Row::string($row, 'client_id'),
            Url::fromString(Row::string($row, 'line_item'), $this->allowLocalhost),
            Score::fromArray(\is_array($payload) ? $payload : []),
            new DateTimeImmutable('@'.Row::int($row, 'created_at')),
            null === $dispatched ? null : new DateTimeImmutable('@'.$dispatched),
            null === $refused ? null : new DateTimeImmutable('@'.$refused),
            Row::optionalString($row, 'refusal'),
        );
    }
}
