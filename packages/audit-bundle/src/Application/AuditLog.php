<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application;

use Phil\AuditBundle\Application\Contract\TenantScope;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\AuditLogReader;
use Phil\AuditBundle\Domain\Read\AuditPage;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Cursor;

/**
 * Reads the log: pages of rows found by their facts and their period, the history of one record, one row by its
 * place, and every row of a query for an export. In a tenant — when a `TenantScope` gives one —, every read is held to
 * that tenant's rows, whatever tenant the query names, and every row a reader answers is held to the query once more
 * with `===`, whatever its storage compares. The log is read only: nothing here changes a row.
 */
final readonly class AuditLog
{
    public function __construct(
        private AuditLogReader $reader,
        private ?TenantScope $tenants = null,
    ) {
    }

    public function page(AuditQuery $query): AuditPage
    {
        $query = $this->scoped($query);
        $page = $this->reader->page($query);

        return new AuditPage(array_values(array_filter($page->rows, $query->matches(...))), $page->next);
    }

    /**
     * The history of one record, the earliest first: the changes of state its workflows recorded and every other
     * event about it.
     */
    public function history(Subject $subject, ?Cursor $after = null, int $limit = AuditQuery::DEFAULT_LIMIT): AuditPage
    {
        return $this->page(AuditQuery::historyOf($subject)->limitedTo($limit)->after($after));
    }

    /**
     * One row by its place; `null` when there is none, or when it belongs to another tenant than the current one.
     */
    public function row(string $segment, int $sequence): ?AuditRow
    {
        $row = $this->reader->row($segment, $sequence);
        $tenant = $this->tenants?->current();

        return null === $tenant || $row?->facts->tenant === $tenant ? $row : null;
    }

    /**
     * Every row of the query from its cursor on, read a page of {@see AuditQuery::MAX_LIMIT} at a time: memory holds
     * one page, however long the log.
     *
     * @return iterable<AuditRow>
     */
    public function each(AuditQuery $query): iterable
    {
        $query = $query->limitedTo(AuditQuery::MAX_LIMIT);

        do {
            $page = $this->page($query);

            yield from $page->rows;

            $query = $query->after($page->next);
        } while (!$page->isLast());
    }

    private function scoped(AuditQuery $query): AuditQuery
    {
        $tenant = $this->tenants?->current();

        return null === $tenant ? $query : $query->inTenant($tenant);
    }
}
