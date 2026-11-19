<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\AttendancePolicy;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\Proposals;
use Phil\CalendarBundle\Application\ProposalWorkflow;
use Phil\CalendarBundle\Domain\Exception\AttendanceRefused;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryAttendances;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

#[CoversClass(Attendees::class)]
#[CoversClass(AttendanceRefused::class)]
#[CoversClass(PersonalCalendar::class)]
#[CoversClass(Proposals::class)]
#[CoversClass(ProposalWorkflow::class)]
final class AttendeesTest extends TestCase
{
    private InMemoryEvents $events;

    private InMemoryAttendances $attendances;

    private Calendar $calendar;

    protected function setUp(): void
    {
        $this->events = new InMemoryEvents();
        $this->attendances = new InMemoryAttendances();
        $this->calendar = new Calendar($this->events);
    }

    public function testEachRefusal(): void
    {
        $attendees = new Attendees($this->attendances, new MockClock('2026-11-01 12:00 UTC'));
        $ana = Member::of('member', 'ana');

        $cancelled = $this->event('Annulé');
        $cancelled->cancel();

        $proposed = $this->calendar->schedule($this->draft('Proposé'), Member::of('member', 'ben'));
        $weekly = $this->event('Club', 'FREQ=WEEKLY;COUNT=2');
        $weekly->limitTo(1);

        $attendees->attend($weekly, Member::of('member', 'cleo'));

        $refusals = [
            [$cancelled, AttendanceRefused::CANCELLED, 'The event is cancelled.'],
            [$proposed, AttendanceRefused::NOT_PUBLISHED, 'The event is not published.'],
            [new Occurrence($this->event('Seul'), $this->at('2026-11-10 20:00'), $this->at('2026-11-10 21:00')), AttendanceRefused::NOT_AN_OCCURRENCE, 'The event has no occurrence at that moment.'],
            [$weekly, AttendanceRefused::FULL, 'The event is full.'],
        ];

        foreach ($refusals as [$what, $reason, $message]) {
            try {
                $attendees->attend($what, $ana);
                self::fail($reason);
            } catch (AttendanceRefused $refused) {
                self::assertSame([$reason, $message], [$refused->reason, $refused->getMessage()]);
            }
        }

        self::assertSame([], array_filter($this->attendances->attendances, static fn (object $attendance): bool => 'member:ana' === (string) $attendance->member()));
        self::assertSame('The application does not admit this attendance.', AttendanceRefused::because(AttendanceRefused::POLICY)->getMessage());
    }

    public function testThePolicyIsAskedLast(): void
    {
        $policy = new class implements AttendancePolicy {
            /** @var list<string> */
            public array $asked = [];

            public function admits(Event $event, ?DateTimeImmutable $occurrence, Member $member): bool
            {
                $this->asked[] = $member.' '.($occurrence?->format('m-d H:i') ?? 'every');

                return 'paid' === $member->reference;
            }
        };
        $attendees = new Attendees($this->attendances, new MockClock('2026-11-01 12:00 UTC'), $policy);
        $event = $this->event('Payant');
        [$occurrence] = $event->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00'));

        $attendance = $attendees->attend($occurrence, Member::of('member', 'paid'));
        self::assertSame('2026-11-01 12:00', $attendance->createdAt()->format('Y-m-d H:i'));

        try {
            $attendees->attend($event, Member::of('member', 'free'));
            self::fail('Not paid.');
        } catch (AttendanceRefused $attendanceRefused) {
            self::assertSame(AttendanceRefused::POLICY, $attendanceRefused->reason);
        }

        self::assertSame(['member:paid 11-10 19:00', 'member:free every'], $policy->asked);

        $cancelled = $this->event('Annulé');
        $cancelled->cancel();

        try {
            $attendees->attend($cancelled, Member::of('member', 'paid'));
        } catch (AttendanceRefused) {
            self::assertCount(2, $policy->asked, 'the policy is not asked for an attendance the calendar refuses');
        }
    }

    public function testCountsAndPages(): void
    {
        $attendees = new Attendees($this->attendances, new MockClock());
        $event = $this->event('Grand');
        [$occurrence] = $event->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00'));
        self::assertNull($attendees->placesLeft($occurrence));
        self::assertSame(0, $attendees->count($event));

        for ($i = 0; $i < 3; ++$i) {
            $attendees->attend($occurrence, Member::of('member', (string) $i));
        }

        self::assertSame(['member:0'], array_map(strval(...), $attendees->attendees($occurrence, 0)), 'a page has one attendee at least');
        self::assertSame(['member:0', 'member:1', 'member:2'], array_map(strval(...), $attendees->attendees($occurrence, 1000, -5)));
        self::assertSame(['member:2'], array_map(strval(...), $attendees->attendees($occurrence, 1, 2)));
        self::assertSame(0, $attendees->count($event), 'the event as a whole has none');
        self::assertSame(3, $attendees->count($occurrence));

        $event->limitTo(2);
        self::assertSame(0, $attendees->placesLeft($occurrence), 'never below none');
        self::assertFalse($attendees->withdraw($event, Member::of('member', '0')));
        self::assertTrue($attendees->withdraw($occurrence, Member::of('member', '0')));
        self::assertSame(0, $attendees->placesLeft($occurrence));
        self::assertTrue($attendees->withdraw($occurrence, Member::of('member', '1')));
        self::assertSame(1, $attendees->placesLeft($occurrence));
        self::assertFalse($attendees->isAttending($event, Member::of('member', '2')));
        self::assertTrue($attendees->isAttending($occurrence, Member::of('member', '2')));
    }

    /**
     * Attending every occurrence of an event is attending each of them, and nothing is attended without it.
     */
    public function testEveryOccurrenceIsAttendedAtOnce(): void
    {
        $attendees = new Attendees($this->attendances, new MockClock());
        $weekly = $this->event('Club', 'FREQ=WEEKLY;COUNT=3');
        [$first, $second] = $weekly->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00'));
        $ana = Member::of('member', 'ana');
        $ben = Member::of('member', 'ben');

        self::assertFalse($attendees->isAttending($first, $ana));
        $attendees->attend($weekly, $ana);
        $attendees->attend($second, $ben);

        self::assertTrue($attendees->isAttending($first, $ana));
        self::assertTrue($attendees->isAttending($second, $ana));
        self::assertTrue($attendees->isAttending($weekly, $ana));
        self::assertFalse($attendees->isAttending($first, $ben));
        self::assertTrue($attendees->isAttending($second, $ben));
        self::assertFalse($attendees->isAttending($weekly, $ben), 'one occurrence is not the event');
        self::assertSame(1, $attendees->count($first));
        self::assertSame(2, $attendees->count($second));
    }

    public function testAPageOfAttendeesIsAHundredByDefault(): void
    {
        $attendees = new Attendees($this->attendances, new MockClock());
        $event = $this->event('Foule');

        for ($i = 0; $i < 600; ++$i) {
            $attendees->attend($event, Member::of('member', 'm'.$i));
        }

        self::assertCount(100, $attendees->attendees($event));
        self::assertSame('member:m0', (string) $attendees->attendees($event)[0]);
        self::assertCount(500, $attendees->attendees($event, 100_000));
        self::assertSame('member:m500', (string) $attendees->attendees($event, 100_000, 500)[0]);
    }

    /**
     * The waiting proposals are a hundred by default, one at least and five hundred at most.
     */
    public function testThePagesOfProposals(): void
    {
        $proposals = new Proposals($this->calendar, $this->events);
        $ana = Member::of('member', 'ana');

        for ($i = 0; $i < 520; ++$i) {
            $proposals->propose($this->draft('Idée '.$i), $ana);
        }

        self::assertCount(100, $proposals->waiting());
        self::assertCount(1, $proposals->waiting(0));
        self::assertCount(7, $proposals->waiting(7));
        self::assertCount(500, $proposals->waiting(100_000));

        $saved = \count($this->events->saved);
        $proposals->approve($this->events->proposed(1)[0]);
        self::assertCount($saved + 1, $this->events->saved, 'an approval is saved');
        self::assertSame(['proposed', 'published', 'refused'], array_keys(ProposalWorkflow::definition()->getPlaces()));
    }

    public function testTheProposalsWithoutADispatcher(): void
    {
        $proposals = new Proposals($this->calendar, $this->events);
        $proposal = $proposals->propose($this->draft('Murder'), Member::of('member', 'ana'));

        self::assertSame(Event::PROPOSED, $proposal->getProposalState());
        self::assertSame([$proposal], $proposals->waiting(0));
        self::assertTrue($proposals->can($proposal, ProposalWorkflow::APPROVE));
        self::assertTrue($proposals->can($proposal, ProposalWorkflow::REFUSE));
        self::assertSame([], $this->calendar->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00')));

        $proposals->approve($proposal);
        self::assertTrue($proposal->isPublished());
        self::assertFalse($proposals->can($proposal, ProposalWorkflow::REFUSE));
        self::assertSame([], $proposals->waiting(1000));
        self::assertSame(['phil_calendar_proposal', 'proposed'], [ProposalWorkflow::NAME, ProposalWorkflow::definition()->getInitialPlaces()[0] ?? null]);

        $this->expectException(NotEnabledTransitionException::class);
        $proposals->approve($proposal);
    }

    public function testAPersonalCalendar(): void
    {
        $attendees = new Attendees($this->attendances, new MockClock());
        $personal = new PersonalCalendar($this->calendar, $this->attendances, $this->events);
        $ana = Member::of('member', 'ana');
        $weekly = $this->event('Club', 'FREQ=WEEKLY;COUNT=3');
        $other = $this->event('Autre');
        [, $second] = $weekly->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00'));
        $attendees->attend($second, $ana);
        $attendees->attend($other, Member::of('member', 'ben'));

        $hosted = $this->event('Animé');
        $this->calendar->host($hosted, 'member', 'ana');
        $from = $this->at('2026-11-01 00:00');
        $through = $this->at('2026-12-01 00:00');

        $titles = static fn (array $occurrences): array => array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title().' '.$occurrence->start->format('m-d'), $occurrences);
        self::assertSame(['Animé 11-10', 'Club 11-17'], $titles($personal->occurrences($ana, $from, $through)));
        self::assertSame(['Club 11-17'], $titles($personal->occurrences($ana, $from, $through, false)));
        self::assertSame(['Autre 11-10'], $titles($personal->occurrences(Member::of('member', 'ben'), $from, $through)));

        // Attending the event it hosts too: once.
        $attendees->attend($hosted, $ana);
        self::assertSame(['Animé 11-10', 'Club 11-17'], $titles($personal->occurrences($ana, $from, $through)));
    }

    private function event(string $title, ?string $rule = null): Event
    {
        return $this->calendar->schedule($this->draft($title, $rule));
    }

    private function draft(string $title, ?string $rule = null): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable('2026-11-10 19:00');
        $draft->end = new DateTimeImmutable('2026-11-10 21:00');
        $draft->timezone = 'America/Toronto';
        $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);

        return $draft;
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('America/Toronto'));
    }
}
