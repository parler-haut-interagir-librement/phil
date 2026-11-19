<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Whether the application admits a member to an event, after the calendar's own checks: a member of the right group,
 * an attendance paid for, a ticket held. A policy that sells places answers once the order is paid; the calendar takes
 * no payment.
 */
interface AttendancePolicy
{
    /**
     * @param DateTimeImmutable|null $occurrence the start of the occurrence, or null for every occurrence
     */
    public function admits(Event $event, ?DateTimeImmutable $occurrence, Member $member): bool;
}
