<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Message;

/**
 * A row was recorded: its place, and nothing it says. A handler that needs the row reads it back through
 * `AuditLog::row()`, so that no field of the trail travels on a transport.
 */
final readonly class AuditRecorded
{
    public function __construct(
        public string $segment,
        public int $sequence,
    ) {
    }
}
