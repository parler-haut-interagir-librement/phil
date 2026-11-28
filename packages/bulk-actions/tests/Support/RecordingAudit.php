<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Phil\BulkActions\Contract\AuditTrail;
use Phil\BulkActions\Run\AuditEvent;
use Phil\BulkActions\Run\BulkRun;

final class RecordingAudit implements AuditTrail
{
    /** @var list<string> each event as "event:actor:action:size" */
    public array $events = [];

    public function record(AuditEvent $event, BulkRun $run): void
    {
        $this->events[] = $event->value.':'.$run->actor.':'.$run->action.':'.$run->size();
    }
}
