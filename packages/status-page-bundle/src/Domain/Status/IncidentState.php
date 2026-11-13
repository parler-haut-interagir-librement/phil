<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\Status;

/**
 * Where the handling of an incident stands, as each update says it: investigating, identified, monitoring a fix,
 * resolved.
 */
enum IncidentState: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
}
