<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Repository;

use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Where attendances are kept. An occurrence is named by `Attendance::key()`: its start in UTC, or `''` for every
 * occurrence.
 */
interface AttendanceRepository
{
    public function save(Attendance $attendance): void;

    public function remove(Attendance $attendance): void;

    /**
     * The member's attendance of exactly this occurrence, or of every one with `''`.
     */
    public function of(Event $event, string $occurrence, Member $member): ?Attendance;

    /**
     * How many attend an occurrence: those of that occurrence and those of every occurrence.
     */
    public function count(Event $event, string $occurrence): int;

    /**
     * Who attends an occurrence — of that occurrence or of every one —, in the order they came, a page at a time.
     *
     * @return list<Member>
     */
    public function attendees(Event $event, string $occurrence, int $limit, int $offset = 0): array;

    /**
     * What a member attends: the occurrences by event identifier, `''` for every one.
     *
     * @return array<string, list<string>>
     */
    public function attendedBy(Member $member): array;

    /**
     * Removes every attendance of a member.
     */
    public function forgetMember(Member $member): void;
}
