<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Contract;

use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Read\AuditPage;
use Phil\AuditBundle\Domain\Read\AuditQuery;

/**
 * The read side of the trail: rows found by their facts and their instant, a page at a time, on indexes.
 */
interface AuditLogReader
{
    /**
     * The rows that match every filter of the query, in its order, at most its limit, after its cursor.
     */
    public function page(AuditQuery $query): AuditPage;

    /**
     * One row, by its place; `null` when there is none.
     */
    public function row(string $segment, int $sequence): ?AuditRow;
}
