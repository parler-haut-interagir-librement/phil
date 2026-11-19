<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\ExpansionBudget;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Tests\Benchmark\Fixture\SampleEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryAttendances;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryCalendarChoices;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;
use Symfony\Component\Clock\MockClock;

/**
 * A member's own calendar reads the month once and matches each occurrence against their choices; a calendar of rules
 * that never match is held by the budget of the request, whatever its size.
 *
 * @Groups({"calendar"})
 */
final class PersonalCalendarBench
{
    /**
     * @var array<int, PersonalCalendar>
     */
    private array $personal = [];

    /**
     * @var array<int, InMemoryEvents>
     */
    private array $hostile = [];

    private readonly Member $member;

    private readonly DateTimeImmutable $from;

    private readonly DateTimeImmutable $through;

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $this->from = new DateTimeImmutable('2026-11-01 00:00', $zone);
        $this->through = new DateTimeImmutable('2026-11-30 23:59:59', $zone);
        $this->member = Member::of('member', 'ana');

        foreach ([100, 500] as $count) {
            $events = SampleEvents::repository($count);
            $choices = new CalendarChoices(new InMemoryCalendarChoices(), new MockClock());

            foreach (['larp', 'rpg', 'board'] as $tag) {
                $choices->follow($this->member, ChoiceSource::Tag, $tag);
            }

            $choices->hide($this->member, ChoiceSource::Host, 'association:1');
            $this->personal[$count] = new PersonalCalendar(new Calendar($events), new InMemoryAttendances(), $events, $choices);
        }

        foreach ([1000, 5000] as $count) {
            $events = new InMemoryEvents();

            for ($i = 0; $i < $count; ++$i) {
                $event = Event::schedule('Hostile', new DateTimeImmutable('2026-01-05 19:00', $zone), new DateTimeImmutable('2026-01-05 20:00', $zone));
                $event->repeat(RecurrenceRule::parse('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30'));
                $events->events[$event->id] = $event;
            }

            $this->hostile[$count] = $events;
        }
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(2)
     *
     * @Iterations(5)
     *
     * @param array{events: int} $params
     */
    public function benchPersonalMonth(array $params): void
    {
        $this->personal[$params['events']]->occurrences($this->member, $this->from, $this->through);
    }

    /**
     * @ParamProviders("hostile")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     *
     * @param array{rules: int} $params
     */
    public function benchHostileMonthWithinTheBudget(array $params): void
    {
        new Calendar($this->hostile[$params['rules']], null, new ExpansionBudget())->occurrences($this->from, $this->through);
    }

    /**
     * @return Generator<string, array{events: int}>
     */
    public function counts(): Generator
    {
        yield '100 events' => ['events' => 100];
        yield '500 events' => ['events' => 500];
    }

    /**
     * @return Generator<string, array{rules: int}>
     */
    public function hostile(): Generator
    {
        yield '1,000 rules' => ['rules' => 1000];
        yield '5,000 rules' => ['rules' => 5000];
    }
}
