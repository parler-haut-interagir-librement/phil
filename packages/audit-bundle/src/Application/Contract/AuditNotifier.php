<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Contract;

use Phil\AuditBundle\Domain\Chain\AuditRow;

/**
 * Told of every row once it is recorded, to pass it on to a dashboard, a security information and event management
 * system, or an alert. The row is already in the trail: an adapter sends where it is, never what it says.
 */
interface AuditNotifier
{
    public function recorded(AuditRow $row): void;
}
