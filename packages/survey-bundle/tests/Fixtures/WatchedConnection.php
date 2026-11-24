<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Exception;
use Override;

/**
 * A connection that tells the number of rows a statement touched as the text some drivers give, keeps the statements
 * it ran, and deadlocks the first transactions it is asked to begin.
 */
final class WatchedConnection extends Connection
{
    /** @var list<string> */
    public array $statements = [];

    public int $begun = 0;

    public int $committed = 0;

    public int $deadlocks = 0;

    /** Whether a column of digits is given as numbers, as some drivers do. */
    public bool $numbersAsInts = false;

    public function forget(): void
    {
        $this->statements = [];
    }

    /**
     * @return list<string> the statements run that begin with this text
     */
    public function ran(string $prefix): array
    {
        return array_values(array_filter($this->statements, static fn (string $sql): bool => str_starts_with($sql, $prefix)));
    }

    #[Override]
    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        $this->statements[] = $query;
        $column = parent::fetchFirstColumn($query, $params, $types);

        return $this->numbersAsInts ? array_map(static fn (mixed $value): mixed => \is_string($value) && 1 === preg_match('/^\d+$/D', $value) ? (int) $value : $value, $column) : $column;
    }

    #[Override]
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->statements[] = $sql;

        return (string) parent::executeStatement($sql, $params, $types);
    }

    #[Override]
    public function beginTransaction(): void
    {
        ++$this->begun;

        if ($this->deadlocks >= $this->begun) {
            throw new DeadlockException(new class('Deadlock found') extends Exception implements DriverException {
                public function getSQLState(): ?string
                {
                    return '40001';
                }
            }, null);
        }

        parent::beginTransaction();
    }

    #[Override]
    public function commit(): void
    {
        ++$this->committed;

        parent::commit();
    }
}
