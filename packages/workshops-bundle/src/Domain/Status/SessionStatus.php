<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Status;

/**
 * Whether a session takes place: scheduled until it is cancelled, and cancelled for good.
 */
enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
