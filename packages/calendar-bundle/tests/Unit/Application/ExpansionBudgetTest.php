<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\ExpansionBudget;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversClass(ExpansionBudget::class)]
#[CoversClass(Calendar::class)]
final class ExpansionBudgetTest extends TestCase
{
    /**
     * The longest a request of hostile rules may take, the budget's defaults holding it: about 0.6 s on a laptop, more
     * under a coverage driver or a loaded runner; unbounded, the same request takes several times as long.
     */
    private const float SECONDS = 5.0;

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function refusedLimits(): iterable
    {
        yield 'no occurrence' => [0, 1, 'The budget of occurrences is 1 to 1000000.'];
        yield 'too many occurrences' => [1_000_001, 1, 'The budget of occurrences is 1 to 1000000.'];
        yield 'no expansion' => [1, 0, 'The budget of expansions is 1 to 1000000.'];
        yield 'too many expansions' => [1, 1_000_001, 'The budget of expansions is 1 to 1000000.'];
    }

    #[DataProvider('refusedLimits')]
    public function testItsLimitsAreBounded(int $occurrences, int $expansions, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ExpansionBudget($occurrences, $expansions);
    }

    public function testItCountsAndStops(): void
    {
        $budget = new ExpansionBudget(10, 2);
        self::assertSame([10_000, 500, 1_000_000], [ExpansionBudget::OCCURRENCES, ExpansionBudget::EXPANSIONS, ExpansionBudget::CEILING]);
        self::assertSame([1, 1_000_000], [new ExpansionBudget(1, 1_000_000)->maxOccurrences, new ExpansionBudget(1_000_000, 1)->maxOccurrences]);

        // An event that does not repeat costs no expansion.
        self::assertTrue($budget->admits(false));
        self::assertSame(0, $budget->expansions());
        self::assertTrue($budget->admits(true));
        self::assertTrue($budget->admits(true));
        self::assertSame(2, $budget->expansions());
        self::assertSame(4, $budget->limit(4));
        $budget->spend(4);
        self::assertFalse($budget->isExhausted());
        self::assertFalse($budget->shouldReport());

        // Expansions spent: a repeating event is refused, and then every event.
        self::assertFalse($budget->admits(true));
        self::assertSame(2, $budget->expansions());
        self::assertTrue($budget->isExhausted());
        self::assertFalse($budget->admits(false));
        self::assertTrue($budget->shouldReport());
        self::assertFalse($budget->shouldReport(), 'reported once');

        $budget->reset();
        self::assertSame([0, 0, false, false], [$budget->occurrences(), $budget->expansions(), $budget->isExhausted(), $budget->shouldReport()]);

        // Occurrences: those left given, and an event that fills them exhausts the budget.
        self::assertSame(10, $budget->limit(10));
        $budget->spend(10);
        self::assertFalse($budget->isExhausted(), 'exactly what was wanted');
        self::assertFalse($budget->admits(false));
        self::assertTrue($budget->isExhausted());

        $budget->reset();
        $budget->spend(7);
        self::assertSame(3, $budget->limit(5));
        $budget->spend(2);
        self::assertFalse($budget->isExhausted(), 'fewer than were left');
        self::assertSame(1, $budget->limit(5));
        $budget->spend(1);
        self::assertTrue($budget->isExhausted());
        self::assertSame(10, $budget->occurrences());
    }

    public function testTheCalendarStopsAtItsBudgetAndSaysItOnce(): void
    {
        $events = new InMemoryEvents();
        foreach (['A', 'B', 'C'] as $title) {
            $events->save($this->event($title, 'FREQ=DAILY'));
        }

        $events->save($this->event('Seul'));

        $logger = $this->logger();
        $budget = new ExpansionBudget(1000, 2);
        $calendar = new Calendar($events, null, $budget, $logger);
        $from = $this->at('2026-11-01 00:00');
        $through = $this->at('2026-11-07 23:59');

        self::assertFalse($calendar->isCut());
        $titles = array_values(array_unique(array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title(), $calendar->occurrences($from, $through))));
        self::assertSame(['A', 'B'], $titles, 'two repeating events read, the third refused, and every one after it');
        self::assertTrue($calendar->isCut());
        self::assertSame(['warning The calendar stopped reading events at its budget: {occurrences} of {max_occurrences} occurrences, {expansions} of {max_expansions} repeating events. 14 1000 2 2'], $logger->lines);

        // The same request: nothing more, and no second warning.
        self::assertSame([], $calendar->occurrences($from, $through));
        self::assertSame([], $calendar->events($from, $through));
        self::assertCount(1, $logger->lines);

        $budget->reset();
        $afterReset = new Calendar($events, null, $budget, $logger);
        self::assertFalse($afterReset->isCut());
        self::assertSame(['A', 'B'], array_map(static fn (Event $event): string => $event->title(), $afterReset->events($from, $through)));
        self::assertSame(2, $budget->occurrences(), 'one occurrence read for each event');
        self::assertCount(2, $logger->lines);

        // A tag left out costs nothing.
        $budget->reset();
        self::assertSame([], $calendar->occurrences($from, $through, tag: 'none'));
        self::assertSame([], $calendar->events($from, $through, tag: 'none'));
        self::assertSame([0, 0, false], [$budget->occurrences(), $budget->expansions(), $budget->isExhausted()]);
    }

    public function testAnEventIsCutAtTheOccurrencesLeft(): void
    {
        $events = new InMemoryEvents();
        $events->save($this->event('A', 'FREQ=DAILY'));
        $events->save($this->event('B', 'FREQ=DAILY'));

        $budget = new ExpansionBudget(10, 100);
        $calendar = new Calendar($events, null, $budget);

        $occurrences = $calendar->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-11-30 23:59'));
        self::assertCount(10, $occurrences);
        self::assertSame(['A'], array_values(array_unique(array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title(), $occurrences))));
        self::assertTrue($calendar->isCut());
        self::assertSame(1, $budget->expansions(), 'the second event is not read');
    }

    public function testWithoutABudgetEachCallHasTheDefaults(): void
    {
        $events = new InMemoryEvents();
        $events->save($this->event('A', 'FREQ=DAILY'));

        $calendar = new Calendar($events);

        self::assertCount(30, $calendar->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-11-30 23:59')));
        self::assertCount(30, $calendar->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-11-30 23:59')));
        self::assertFalse($calendar->isCut());
        self::assertSame(1000, Calendar::PER_EVENT);
    }

    /**
     * Fifteen hundred rules that never give an occurrence, each walked to the bounds of phil/recurrence: the budget's
     * defaults hold a month, its feed and a member's calendar to a bounded time, and the stop is logged once.
     */
    public function testHostileRulesAreHeldToABoundedTime(): void
    {
        $events = new InMemoryEvents();

        for ($i = 0; $i < 1500; ++$i) {
            $events->save($this->event('Hostile '.$i, 'FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30'));
        }

        // A tenth of the budget first, for the time of one expansion on this machine.
        $tenth = new ExpansionBudget(ExpansionBudget::OCCURRENCES, intdiv(ExpansionBudget::EXPANSIONS, 10));
        $started = hrtime(true);
        new Calendar($events, null, $tenth)->occurrences($this->at('2026-11-01 00:00'), $this->at('2027-10-31 23:59'));
        $tenthSeconds = (hrtime(true) - $started) / 1e9;

        $logger = $this->logger();
        $budget = new ExpansionBudget();
        $calendar = new Calendar($events, null, $budget, $logger);
        $started = hrtime(true);

        self::assertSame([], $calendar->occurrences($this->at('2026-11-01 00:00'), $this->at('2027-10-31 23:59')));
        self::assertSame([], $calendar->events($this->at('2026-10-01 00:00'), $this->at('2027-11-01 00:00')));

        $seconds = (hrtime(true) - $started) / 1e9;
        self::assertLessThan(self::SECONDS, $seconds, \sprintf('%.2f s for 1,500 hostile rules', $seconds));
        self::assertLessThan(20 * $tenthSeconds + 0.5, $seconds, 'the time grows with the budget, not with the events');
        self::assertSame(ExpansionBudget::EXPANSIONS, $budget->expansions());
        self::assertSame(intdiv(ExpansionBudget::EXPANSIONS, 10), $tenth->expansions());
        self::assertCount(1, $logger->lines);
    }

    public function testOnlyRepeatingEventsCostAnExpansion(): void
    {
        $events = new InMemoryEvents();
        $events->save($this->event('Seul 1'));
        $events->save($this->event('Seul 2'));
        $events->save($this->event('Seul 3'));

        $from = $this->at('2026-01-01 00:00');
        $through = $this->at('2026-01-31 23:59');

        // Events that do not repeat cost none: all are read with one expansion allowed.
        $budget = new ExpansionBudget(1000, 1);
        $calendar = new Calendar($events, null, $budget);
        self::assertCount(3, $calendar->occurrences($from, $through));
        self::assertCount(3, $calendar->events($from, $through));
        self::assertSame(0, $budget->expansions());
        self::assertFalse($budget->isExhausted());

        // Dates added make an event repeat, with no rule.
        $dated = new InMemoryEvents();
        foreach (['Dates 1', 'Dates 2'] as $title) {
            $event = $this->event($title);
            $event->addDate($this->at('2026-01-12 19:00'));
            $dated->save($event);
        }

        $budget = new ExpansionBudget(1000, 1);
        $titles = array_map(static fn (Event $event): string => $event->title(), new Calendar($dated, null, $budget)->events($from, $through));
        self::assertSame(['Dates 1'], $titles);
        self::assertSame(1, $budget->expansions());
        self::assertTrue($budget->isExhausted());
    }

    public function testEventsOfAnotherTagAreSkippedNotTheEnd(): void
    {
        $events = new InMemoryEvents();
        $first = $this->event('Sans étiquette');
        $second = $this->event('Étiqueté');
        $second->tag(['jeux']);

        $events->save($first);
        $events->save($second);

        $found = new Calendar($events)->events($this->at('2026-01-01 00:00'), $this->at('2026-01-31 23:59'), tag: 'jeux');

        self::assertSame(['Étiqueté'], array_map(static fn (Event $event): string => $event->title(), $found));
    }

    public function testAResetForgetsAReducedLimit(): void
    {
        $budget = new ExpansionBudget(10, 5);
        $budget->spend(5);
        self::assertSame(5, $budget->limit(8), 'fewer than wanted');

        $budget->reset();
        $budget->spend(10);
        self::assertFalse($budget->isExhausted(), 'no limit was reduced since the reset');
    }

    /**
     * @return AbstractLogger&object{lines: list<string>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                \assert(\is_string($level));
                $this->lines[] = trim($level.' '.$message.' '.implode(' ', array_map(static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '', $context)));
            }
        };
    }

    private function event(string $title, ?string $rule = null): Event
    {
        $event = Event::schedule($title, $this->at('2026-01-05 19:00'), $this->at('2026-01-05 20:00'), false, 'America/Toronto');
        $event->repeat(null === $rule ? null : RecurrenceRule::parse($rule));

        return $event;
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('America/Toronto'));
    }
}
