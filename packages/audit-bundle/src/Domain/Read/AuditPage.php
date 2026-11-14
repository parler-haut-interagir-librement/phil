<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Read;

use Phil\AuditBundle\Domain\Chain\AuditRow;

/**
 * A page of the log: its rows, in the order asked, and the cursor of the next page, `null` on the last one.
 */
final readonly class AuditPage
{
    /**
     * @param list<AuditRow> $rows
     */
    public function __construct(
        public array $rows,
        public ?Cursor $next,
    ) {
    }

    public function isLast(): bool
    {
        return !$this->next instanceof Cursor;
    }
}
