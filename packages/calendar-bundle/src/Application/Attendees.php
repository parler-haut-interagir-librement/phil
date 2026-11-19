<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use Phil\CalendarBundle\Domain\Exception\AttendanceRefused;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Psr\Clock\ClockInterface;

/**
 * Who attends an event: a member signs up for one occurrence, or for all of them by attending the event itself.
 *
 * A cancelled event, one not published, an occurrence the event does not have, or a full one — its capacity reached by
 * those of that occurrence and those of every occurrence — is refused with `AttendanceRefused`, then the application's
 * `AttendancePolicy`, if any, is asked. A member signs up once: signing up again gives the attendance kept.
 */
final readonly class Attendees
{
    /** The most attendees a page lists. */
    public const int PAGE = 500;

    public function __construct(
        private AttendanceRepository $attendances,
        private ClockInterface $clock,
        private ?AttendancePolicy $policy = null,
    ) {
    }

    /**
     * @throws AttendanceRefused
     */
    public function attend(Occurrence|Event $what, Member $member): Attendance
    {
        [$event, $start] = $this->of($what);
        $kept = $this->attendances->of($event, Attendance::key($start), $member);

        if ($kept instanceof Attendance) {
            return $kept;
        }

        $refusal = match (true) {
            $event->isCancelled() => AttendanceRefused::CANCELLED,
            !$event->isPublished() => AttendanceRefused::NOT_PUBLISHED,
            null !== $start && !$this->startsAt($event, $start) => AttendanceRefused::NOT_AN_OCCURRENCE,
            null !== $event->capacity() && $this->attendances->count($event, Attendance::key($start)) >= $event->capacity() => AttendanceRefused::FULL,
            $this->policy instanceof AttendancePolicy && !$this->policy->admits($event, $start, $member) => AttendanceRefused::POLICY,
            default => null,
        };

        if (null !== $refusal) {
            throw AttendanceRefused::because($refusal);
        }

        $attendance = new Attendance($event, $start, $member, $this->clock->now());
        $this->attendances->save($attendance);

        return $attendance;
    }

    /**
     * Takes the member's attendance away; false when there was none.
     */
    public function withdraw(Occurrence|Event $what, Member $member): bool
    {
        [$event, $start] = $this->of($what);
        $attendance = $this->attendances->of($event, Attendance::key($start), $member);

        if (!$attendance instanceof Attendance) {
            return false;
        }

        $this->attendances->remove($attendance);

        return true;
    }

    public function isAttending(Occurrence|Event $what, Member $member): bool
    {
        [$event, $start] = $this->of($what);

        return $this->attendances->of($event, Attendance::key($start), $member) instanceof Attendance
            || (null !== $start && $this->attendances->of($event, Attendance::EVERY_OCCURRENCE, $member) instanceof Attendance);
    }

    /**
     * How many attend: an occurrence, with those of every occurrence; or the event as a whole.
     */
    public function count(Occurrence|Event $what): int
    {
        [$event, $start] = $this->of($what);

        return $this->attendances->count($event, Attendance::key($start));
    }

    /**
     * The places left, or null for an event without a capacity.
     */
    public function placesLeft(Occurrence|Event $what): ?int
    {
        [$event] = $this->of($what);
        $capacity = $event->capacity();

        return null === $capacity ? null : max(0, $capacity - $this->count($what));
    }

    /**
     * Who attends, in the order they came, a page of at most 500 at a time.
     *
     * @return list<Member>
     */
    public function attendees(Occurrence|Event $what, int $limit = 100, int $offset = 0): array
    {
        [$event, $start] = $this->of($what);

        return $this->attendances->attendees($event, Attendance::key($start), max(1, min(self::PAGE, $limit)), max(0, $offset));
    }

    /**
     * Whether one of the event's occurrences starts at that moment, an occurrence taken away excepted.
     */
    private function startsAt(Event $event, DateTimeImmutable $start): bool
    {
        return array_any($event->occurrences($start, $start, 10), static fn (Occurrence $occurrence): bool => $occurrence->start->getTimestamp() === $start->getTimestamp());
    }

    /**
     * @return array{Event, ?DateTimeImmutable}
     */
    private function of(Occurrence|Event $what): array
    {
        return $what instanceof Occurrence ? [$what->event, $what->start] : [$what, null];
    }
}
