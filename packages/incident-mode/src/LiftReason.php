<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

/**
 * Why an incident ended: its duration ran out, or someone lifted it before.
 */
enum LiftReason: string
{
    case Expired = 'expired';
    case Manual = 'manual';
}
