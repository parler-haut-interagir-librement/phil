<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Calendar::class)]
#[CoversClass(EventDraft::class)]
final class CalendarTest extends TestCase
{
    /**
     * @param list<string> $tags
     */
    private function draft(string $title, string $start, string $end, ?string $rule = null, array $tags = []): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable($start, new DateTimeZone('UTC'));
        $draft->end = new DateTimeImmutable($end, new DateTimeZone('UTC'));
        $draft->timezone = 'America/Toronto';
        $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);
        $draft->description = 'Bienvenue.';
        $draft->location = 'Café X';
        $draft->url = 'https://example.com/';
        $draft->tags = $tags;

        return $draft;
    }

    public function testAnEventIsScheduledFromADraftOnTheWallClockOfItsTimeZone(): void
    {
        $events = new InMemoryEvents();
        $event = new Calendar($events)->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY;COUNT=2', ['jeux']));

        self::assertSame($event, $events->get($event->id));
        self::assertSame(['Soirée', '2026-11-10T19:00:00-05:00', '2026-11-10T22:00:00-05:00', 'Bienvenue.', 'Café X', 'https://example.com/', 'FREQ=WEEKLY;COUNT=2', ['jeux']], [$event->title(), $event->start()->format('c'), $event->end()->format('c'), $event->description(), $event->location(), $event->url(), (string) $event->rule(), $event->tags()]);

        $draft = EventDraft::of($event);
        self::assertSame(['Soirée', '2026-11-10T19:00:00-05:00', 'America/Toronto', false, 'FREQ=WEEKLY;COUNT=2', ['jeux'], 'Bienvenue.', 'Café X', 'https://example.com/', '2026-11-10T22:00:00-05:00'], [$draft->title, $draft->start?->format('c'), $draft->timezone, $draft->allDay, (string) $draft->rule, $draft->tags, $draft->description, $draft->location, $draft->url, $draft->end?->format('c')]);
    }

    public function testAnEventIsChangedFromADraft(): void
    {
        $events = new InMemoryEvents();
        $calendar = new Calendar($events);
        $event = $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00'));
        $draft = $this->draft('Festival', '2026-07-10 00:00', '2026-07-12 00:00');
        $draft->allDay = true;
        $draft->timezone = 'Europe/Paris';
        $draft->url = null;

        $calendar->apply($event, $draft);

        self::assertSame(['Festival', '2026-07-10T00:00:00+02:00', true, 'Europe/Paris', null], [$event->title(), $event->start()->format('c'), $event->isAllDay(), $event->timezone()->getName(), $event->url()]);
    }

    public function testTheCapacityOfADraftIsTheEventsAndLeftOutWhenEmpty(): void
    {
        $calendar = new Calendar(new InMemoryEvents());
        $draft = $this->draft('Limité', '2026-11-10 19:00', '2026-11-10 22:00');
        $draft->capacity = 12;

        $event = $calendar->schedule($draft);
        self::assertSame(12, $event->capacity());

        $draft->capacity = null;
        $calendar->apply($event, $draft);
        self::assertNull($event->capacity());
    }

    public function testAnEditThatLeavesTheRuleAloneKeepsTheOccurrencesTakenAway(): void
    {
        $calendar = new Calendar(new InMemoryEvents());
        $event = $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY;COUNT=4'));
        $event->except(new DateTimeImmutable('2026-11-17 19:00', new DateTimeZone('America/Toronto')));

        $draft = EventDraft::of($event);
        $draft->description = 'Apportez vos jeux.';
        $draft->rule = RecurrenceRule::parse('FREQ=WEEKLY;COUNT=4');

        $calendar->apply($event, $draft);

        self::assertSame(['Apportez vos jeux.', 1], [$event->description(), \count($event->exceptions())]);

        $draft->rule = RecurrenceRule::parse('FREQ=WEEKLY;COUNT=5');
        $calendar->apply($event, $draft);
        self::assertSame([], $event->exceptions());
    }

    public function testTheEventsOfAPeriodAreThoseWithAnOccurrenceInIt(): void
    {
        $calendar = new Calendar(new InMemoryEvents());
        $weekly = $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY', ['jeux']));
        $calendar->schedule($this->draft('Atelier', '2026-11-12 19:00', '2026-11-12 22:00'));
        $gap = $calendar->schedule($this->draft('Marché', '2026-11-01 10:00', '2026-11-01 12:00', 'FREQ=MONTHLY;BYMONTHDAY=1'));
        $from = new DateTimeImmutable('2026-11-15 00:00', new DateTimeZone('America/Toronto'));
        $through = new DateTimeImmutable('2026-11-20 00:00', new DateTimeZone('America/Toronto'));

        self::assertSame([$weekly], $calendar->events($from, $through));
        self::assertSame([$weekly], $calendar->events($from, $through, tag: 'jeux'));
        self::assertSame([], $calendar->events($from, $through, tag: 'autre'));
        self::assertSame([$weekly, $gap], $calendar->events($from, $through->modify('+12 days')));
    }

    /**
     * An event is in a period given in UTC even when the days before it, across a change of time, hold one
     * occurrence more than they have days.
     */
    public function testAnEventIsInAPeriodAcrossAChangeOfTime(): void
    {
        $calendar = new Calendar(new InMemoryEvents());
        $morning = $calendar->schedule($this->draft('Café', '2026-03-01 10:00', '2026-03-01 10:15', 'FREQ=DAILY'));
        $utc = new DateTimeZone('UTC');

        self::assertSame([$morning], $calendar->events(new DateTimeImmutable('2026-03-09 14:30', $utc), new DateTimeImmutable('2026-03-10 23:00', $utc)));
    }

    public function testADraftIsScheduledOnceChecked(): void
    {
        $draft = $this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00');
        $draft->timezone = null;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A draft is scheduled once checked: with its start, its end and its time zone.');

        new Calendar(new InMemoryEvents())->schedule($draft);
    }

    public function testADraftWithoutStartOrEndIsNotScheduled(): void
    {
        foreach (['start', 'end'] as $missing) {
            $draft = $this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00');
            $draft->{$missing} = null;

            try {
                new Calendar(new InMemoryEvents())->schedule($draft);
                self::fail('A draft without '.$missing.' was scheduled.');
            } catch (LogicException $logicException) {
                self::assertStringStartsWith('A draft is scheduled once checked', $logicException->getMessage());
            }
        }
    }

    public function testHostsAreAddedOnceAndTakenAway(): void
    {
        $events = new InMemoryEvents();
        $calendar = new Calendar($events);
        $event = $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00'));

        $calendar->host($event, 'association', '42');
        $calendar->host($event, 'association', '42');
        $calendar->host($event, 'shop', '7');
        self::assertCount(2, $events->hostsOf($event));

        $calendar->unhost($event, 'association', '42');
        $calendar->unhost($event, 'association', '43');
        self::assertSame(['shop'], array_map(static fn (EventHost $host): string => $host->kind, $events->hostsOf($event)));
    }

    public function testTheOccurrencesOfAPeriodAreInOrder(): void
    {
        $events = new InMemoryEvents();
        $calendar = new Calendar($events);
        $weekly = $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY', ['jeux']));
        $calendar->schedule($this->draft('Atelier', '2026-11-12 19:00', '2026-11-12 21:00', null, ['peinture']));
        $calendar->schedule($this->draft('Avant', '2026-11-17 19:00', '2026-11-17 20:00'));
        $calendar->host($weekly, 'association', '42');

        $from = new DateTimeImmutable('2026-11-09 00:00', new DateTimeZone('America/Toronto'));
        $through = new DateTimeImmutable('2026-11-18 00:00', new DateTimeZone('America/Toronto'));

        self::assertSame(['Soirée 11-10', 'Atelier 11-12', 'Avant 11-17', 'Soirée 11-17'], $this->titles($calendar->occurrences($from, $through)));
        self::assertSame(['Soirée 11-10', 'Soirée 11-17'], $this->titles($calendar->occurrences($from, $through, 'association', '42')));
        self::assertSame(['Atelier 11-12'], $this->titles($calendar->occurrences($from, $through, tag: 'peinture')));
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<string>
     */
    private function titles(array $occurrences): array
    {
        return array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title().' '.$occurrence->start->format('m-d'), $occurrences);
    }
}
