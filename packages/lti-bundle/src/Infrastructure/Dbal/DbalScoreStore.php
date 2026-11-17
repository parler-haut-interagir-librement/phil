<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\Lti\Contract\ScoreStore;
use Phil\Lti\Service\Ags\Score;

/**
 * The latest score of each learner on each line item, in a table: the score as AGS writes it, read again by the
 * library, and its moment in microseconds since the epoch for ordering.
 *
 * A score is kept by statements that each decide on their own, with no read before them: an update guarded by the
 * moment kept, else an insert, and when another write inserted the row first, the guarded update again. The moment of a
 * row only ever grows, on SQLite, MySQL, MariaDB and PostgreSQL alike, whatever the isolation level; the insert runs in
 * a transaction of its own, or a savepoint in the caller's, so that its refusal leaves a PostgreSQL transaction usable.
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
    public function record(string $lineItemId, Score $score): bool
    {
        $at = $score->timestamp->getTimestamp() * 1_000_000 + (int) $score->timestamp->format('u');
        $payload = json_encode($score->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        if ($this->replace($lineItemId, $score->userId, $payload, $at)) {
            return true;
        }

        try {
            $this->connection->transactional(function (Connection $connection) use ($lineItemId, $score, $payload, $at): void {
                $connection->insert($this->tables->scores, ['line_item_id' => $lineItemId, 'user_id' => $score->userId, 'payload' => $payload, 'scored_at' => $at]);
            });

            return true;
        } catch (UniqueConstraintViolationException) {
            // A score of the same learner was written since: it is compared again, as the first one was.
        }

        if ($this->replace($lineItemId, $score->userId, $payload, $at)) {
            return true;
        }

        // MySQL counts the rows it changes, not the rows it matches: a score written again as it is changes none.
        $kept = $this->connection->fetchFirstColumn(\sprintf('SELECT scored_at FROM %s WHERE line_item_id = ? AND user_id = ?', $this->tables->scores), [$lineItemId, $score->userId]);

        return \in_array($at, array_map(static fn (mixed $moment): int => Row::int(['scored_at' => $moment], 'scored_at'), $kept), true);
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

    /**
     * Replaces the score kept unless it is more recent, in one statement: the row is changed only when its moment is
     * not after the one given, so that no write ever replaces a score by an older one.
     *
     * @phpstan-impure
     */
    private function replace(string $lineItemId, string $userId, string $payload, int $at): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET payload = ?, scored_at = ? WHERE line_item_id = ? AND user_id = ? AND scored_at <= ?', $this->tables->scores),
            [$payload, $at, $lineItemId, $userId, $at],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    private static function read(string $payload): Score
    {
        $data = json_decode($payload, true, 8, \JSON_THROW_ON_ERROR);

        return Score::fromArray(\is_array($data) ? $data : []);
    }
}
