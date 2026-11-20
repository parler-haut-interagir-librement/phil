<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\Recurrence\Frequency;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;

/**
 * When a completion is renewed: the next occurrence of a monthly rule of that interval from the completion, read by
 * phil/recurrence, at the same time of day. A completion on a day the month lacks — the 31st — is renewed at the next
 * month that has it, as RFC 5545 counts a monthly rule.
 */
final class Recertification
{
    private function __construct()
    {
    }

    /**
     * @param int $months 1 to 120, as an assignment holds
     *
     * @throws InvalidCourse when the renewal falls after the year 9999
     */
    public static function renewsAt(DateTimeImmutable $completedAt, int $months): DateTimeImmutable
    {
        return Recurrence::of($completedAt, RecurrenceRule::of(Frequency::Monthly, $months))->next($completedAt)
            ?? throw InvalidCourse::because('A completion is renewed before the end of the year 9999.');
    }
}
