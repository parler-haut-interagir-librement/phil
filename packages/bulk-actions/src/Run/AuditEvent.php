<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

/**
 * What an audit trail is told of a run.
 */
enum AuditEvent: string
{
    case Confirmed = 'confirmed';
    case Finished = 'finished';
    case Stopped = 'stopped';
    case Retried = 'retried';
}
