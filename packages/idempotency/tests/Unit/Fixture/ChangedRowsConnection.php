<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Override;

/**
 * A SQLite connection whose updates report the rows they changed, as MySQL does, and not the rows they matched: an
 * update that writes the values a row already has reports none. It counts the single values it reads.
 */
final class ChangedRowsConnection extends Connection
{
    private int $reads = 0;

    /**
     * How many single values were read since the last call, which starts the count again.
     *
     * @phpstan-impure
     */
    public function reads(): int
    {
        [$reads, $this->reads] = [$this->reads, 0];

        return $reads;
    }

    #[Override]
    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        ++$this->reads;

        return parent::fetchOne($query, $params, $types);
    }

    #[Override]
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if (!str_starts_with($sql, 'UPDATE ')) {
            return parent::executeStatement($sql, $params, $types);
        }

        $before = $this->fetchAllAssociative('SELECT * FROM phil_idempotency ORDER BY scope, idempotency_key');
        parent::executeStatement($sql, $params, $types);
        $after = $this->fetchAllAssociative('SELECT * FROM phil_idempotency ORDER BY scope, idempotency_key');

        return \count(array_udiff($after, $before, static fn (array $a, array $b): int => serialize($a) <=> serialize($b)));
    }
}
