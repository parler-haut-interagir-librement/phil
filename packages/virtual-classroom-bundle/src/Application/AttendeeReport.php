<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application;

use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\ParticipantId;

/**
 * What a participant attended of a session: their stays, the seconds within the session counted once, and the share
 * of the session in whole percents rounded down.
 */
final readonly class AttendeeReport
{
    /**
     * @param list<Interval> $stays
     * @param int<0, max>    $seconds
     * @param int<0, 100>    $percent
     */
    public function __construct(
        public ParticipantId $participant,
        public array $stays,
        public int $seconds,
        public int $percent,
    ) {
    }
}
