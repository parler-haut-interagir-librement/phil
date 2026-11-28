<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

/**
 * The status of an event (RFC 5545, `STATUS`); an event that does not say is confirmed.
 */
enum EventStatus: string
{
    case Tentative = 'TENTATIVE';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
}
