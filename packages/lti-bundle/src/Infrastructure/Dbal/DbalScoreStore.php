<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Override;
use Phil\Lti\Contract\ScoreStore;
use Phil\Lti\Service\Ags\Score;

/**
 * The latest score of each learner on each line item, in a table: the score as AGS writes it, read again by the
 * library, and its moment to the microsecond for ordering.
 */
final readonly class DbalScoreStore implements ScoreStore
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
    ) {
    }

    #[Override]
    public function latest(string $lineItemId, string $userId): ?Score
    {
        $payload = $this->connection->fetchOne(\sprintf('SELECT payload FROM %s WHERE line_item_id = ? AND user_id = ?', $this->tables->scores), [$lineItemId, $userId]);

        return \is_string($payload) ? self::read($payload) : null;
    }

    #[Override]
    public function record(string $lineItemId, Score $score): void
    {
        $row = ['payload' => json_encode($score->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), 'scored_at' => (int) $score->timestamp->format('Uu')];

        $this->connection->transactional(function (Connection $connection) use ($lineItemId, $score, $row): void {
            if (0 === $connection->update($this->tables->scores, $row, ['line_item_id' => $lineItemId, 'user_id' => $score->userId])) {
                $connection->insert($this->tables->scores, ['line_item_id' => $lineItemId, 'user_id' => $score->userId] + $row);
            }
        });
    }

    #[Override]
    public function scores(string $lineItemId, ?string $userId, int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('payload')
            ->from($this->tables->scores)
            ->where('line_item_id = :item')
            ->setParameter('item', $lineItemId)
            ->orderBy('user_id')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (null !== $userId) {
            $query->andWhere('user_id = :user')->setParameter('user', $userId);
        }

        return array_map(static fn (mixed $payload): Score => self::read(\is_string($payload) ? $payload : ''), $query->executeQuery()->fetchFirstColumn());
    }

    private static function read(string $payload): Score
    {
        $data = json_decode($payload, true, 8, \JSON_THROW_ON_ERROR);

        return Score::fromArray(\is_array($data) ? $data : []);
    }
}
