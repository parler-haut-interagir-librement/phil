<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Messenger;

/**
 * Lift every incident past its end: the recurring message of a schedule, in case a delayed message was lost.
 */
final readonly class LiftExpiredIncidents
{
}
