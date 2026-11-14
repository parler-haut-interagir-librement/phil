<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AuditLogReader;
use Phil\AuditBundle\Domain\Read\AuditPage;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Cursor;
use Phil\AuditBundle\Domain\Read\Order;

/**
 * The log read from the trail's table with DBAL, a page at a time: each filter an equality on an indexed column, and
 * the next page found after the key of the last row — instant, segment, sequence — rather than by an offset, so that
 * the thousandth page costs what the first does. One row more than the limit is read to know whether a next page
 * exists.
 *
 * Every row read is held to the query's filters once more in PHP, with `===`: a table whose columns kept a collation
 * that folds case or accents — created before its columns took the binary one, or by hand — would answer `acme` with
 * the rows of `ACME` and `acmé`, and they are left out. The next page is found after the last row read, kept or not.
 */
final readonly class DbalAuditLogReader implements AuditLogReader
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function page(AuditQuery $query): AuditPage
    {
        [$where, $parameters, $types] = $this->conditions($query);
        $direction = Order::NewestFirst === $query->order ? 'DESC' : 'ASC';
        $sql = \sprintf(
            'SELECT %s FROM %s%s ORDER BY recorded_at %4$s, segment %4$s, sequence %4$s LIMIT %5$d',
            DbalAuditRowRepository::COLUMNS,
            AuditTable::NAME,
            [] === $where ? '' : ' WHERE '.implode(' AND ', $where),
            $direction,
            $query->limit + 1,
        );

        $rows = array_map(DbalAuditRowRepository::rowOf(...), $this->connection->fetchAllAssociative($sql, $parameters, $types));
        $next = null;

        if (\count($rows) > $query->limit) {
            $rows = \array_slice($rows, 0, $query->limit);
            $next = Cursor::after($rows[$query->limit - 1]);
        }

        return new AuditPage(array_values(array_filter($rows, $query->matches(...))), $next);
    }

    #[Override]
    public function row(string $segment, int $sequence): ?AuditRow
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM %s WHERE segment = ? AND sequence = ?', DbalAuditRowRepository::COLUMNS, AuditTable::NAME),
            [$segment, $sequence],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
        $row = false === $row ? null : DbalAuditRowRepository::rowOf($row);

        return $row?->segment === $segment ? $row : null;
    }

    /**
     * @return array{list<string>, list<int|string>, list<ParameterType>}
     */
    private function conditions(AuditQuery $query): array
    {
        $where = [];
        $parameters = [];

        foreach (['actor' => $query->actor, 'subject_type' => $query->subjectType, 'subject_id' => $query->subjectId, 'segment' => $query->segment, 'tenant' => $query->tenant] as $column => $value) {
            if (null !== $value) {
                $where[] = $column.' = ?';
                $parameters[] = $value;
            }
        }

        if (null !== $query->action) {
            $where[] = $query->actionIsPrefix ? "action LIKE ? ESCAPE '!'" : 'action = ?';
            $parameters[] = $query->actionIsPrefix ? $this->escapedPrefix($query->action) : $query->action;
        }

        if (null !== $query->sinceInstant()) {
            $where[] = 'recorded_at >= ?';
            $parameters[] = $query->sinceInstant();
        }

        if (null !== $query->untilInstant()) {
            $where[] = 'recorded_at < ?';
            $parameters[] = $query->untilInstant();
        }

        $types = array_map(static fn (): ParameterType => ParameterType::STRING, $parameters);

        if ($query->after instanceof Cursor) {
            $operator = Order::NewestFirst === $query->order ? '<' : '>';
            // The bound on the instant alone comes first: an index ranges over it, where the disjunction alone would
            // have the database scan every row before the cursor. The rest breaks the ties of one instant.
            $where[] = \sprintf('recorded_at %1$s= ? AND (recorded_at %1$s ? OR (recorded_at = ? AND segment %1$s ?) OR (recorded_at = ? AND segment = ? AND sequence %1$s ?))', $operator);
            $cursor = $query->after;
            $parameters = [...$parameters, $cursor->instant, $cursor->instant, $cursor->instant, $cursor->segment, $cursor->instant, $cursor->segment, $cursor->sequence];
            $types = [...$types, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER];
        }

        return [$where, $parameters, $types];
    }

    /**
     * The action as the start of a `LIKE` pattern: an action may hold `_`, which `LIKE` reads as any character, so it
     * is escaped with `!`.
     */
    private function escapedPrefix(string $action): string
    {
        return str_replace('_', '!_', $action).'%';
    }
}
