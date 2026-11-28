<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

use Phil\BulkActions\Run\AuditEvent;
use Phil\BulkActions\Run\BulkRun;

/**
 * Where the application records who ran which bulk action on how many items.
 */
interface AuditTrail
{
    public function record(AuditEvent $event, BulkRun $run): void;
}
