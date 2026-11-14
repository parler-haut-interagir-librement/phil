<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Contract;

/**
 * The tenant the current unit of work acts in, as a reference: rows recorded in it are stamped with it, and reading
 * the log in it sees its rows only.
 */
interface TenantScope
{
    /**
     * @return string|null `null` outside any tenant: rows are recorded without one, and the log is read whole
     */
    public function current(): ?string;
}
