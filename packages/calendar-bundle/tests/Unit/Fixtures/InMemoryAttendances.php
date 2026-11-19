<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use Override;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

final class InMemoryAttendances implements AttendanceRepository
{
    /** @var array<string, Attendance> */
    public array $attendances = [];

    #[Override]
    public function save(Attendance $attendance): void
    {
        $this->attendances[$attendance->id] = $attendance;
    }

    #[Override]
    public function remove(Attendance $attendance): void
    {
        unset($this->attendances[$attendance->id]);
    }

    #[Override]
    public function of(Event $event, string $occurrence, Member $member): ?Attendance
    {
        foreach ($this->attendances as $attendance) {
            if ($attendance->event === $event && Attendance::key($attendance->occurrence()) === $occurrence && $attendance->member()->equals($member)) {
                return $attendance;
            }
        }

        return null;
    }

    #[Override]
    public function count(Event $event, string $occurrence): int
    {
        return \count($this->ofOccurrence($event, $occurrence));
    }

    #[Override]
    public function attendees(Event $event, string $occurrence, int $limit, int $offset = 0): array
    {
        return \array_slice(array_map(static fn (Attendance $attendance): Member => $attendance->member(), $this->ofOccurrence($event, $occurrence)), $offset, $limit);
    }

    #[Override]
    public function attendedBy(Member $member): array
    {
        $attended = [];

        foreach ($this->attendances as $attendance) {
            if ($attendance->member()->equals($member)) {
                $attended[$attendance->event->id][] = Attendance::key($attendance->occurrence());
            }
        }

        return $attended;
    }

    #[Override]
    public function forgetMember(Member $member): void
    {
        $this->attendances = array_filter($this->attendances, static fn (Attendance $attendance): bool => !$attendance->member()->equals($member));
    }

    /**
     * @return list<Attendance>
     */
    private function ofOccurrence(Event $event, string $occurrence): array
    {
        return array_values(array_filter($this->attendances, static fn (Attendance $attendance): bool => $attendance->event === $event && \in_array(Attendance::key($attendance->occurrence()), [$occurrence, Attendance::EVERY_OCCURRENCE], true)));
    }
}
