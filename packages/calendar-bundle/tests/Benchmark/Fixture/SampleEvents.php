<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Benchmark\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;

/**
 * A calendar's events as a shop or a club keeps them: one-off events this month, weekly game nights and monthly
 * meetings that started up to ten years ago, a few whole-day fairs.
 */
final class SampleEvents
{
    private const array RULES = [
        null,
        'FREQ=WEEKLY;BYDAY=TU,TH',
        'FREQ=MONTHLY;BYDAY=1SA',
        'FREQ=WEEKLY;INTERVAL=2;BYDAY=FR',
        'FREQ=DAILY;UNTIL=20271231',
    ];

    /**
     * @return list<Event>
     */
    public static function of(int $count): array
    {
        $zone = new DateTimeZone('America/Toronto');
        $events = [];

        for ($index = 0; $index < $count; ++$index) {
            $rule = self::RULES[$index % \count(self::RULES)];
            $allDay = 0 === $index % 7;
            $start = null === $rule
                ? new DateTimeImmutable(\sprintf('2026-11-%02d 19:00', 1 + $index % 28), $zone)
                : new DateTimeImmutable(\sprintf('%d-%02d-%02d 18:30', 2016 + $index % 10, 1 + $index % 12, 1 + $index % 28), $zone);
            $end = $allDay ? $start->setTime(0, 0)->modify('+1 day') : $start->modify('+3 hours');

            $event = Event::schedule(\sprintf('Event %d; games, cards, and more', $index), $allDay ? $start->setTime(0, 0) : $start, $end, $allDay);
            $event->describe("Bring your dice.\nTables open at six; everyone is welcome, beginners too.", 'Café X, 123 rue Saint-Denis, Montréal', 'https://example.com/events/'.$index);
            $event->tag(['rpg', 0 === $index % 2 ? 'board games' : 'cards']);
            $event->repeat(null === $rule ? null : RecurrenceRule::parse($rule));

            if (null !== $rule && 0 === $index % 3) {
                $event->except(new DateTimeImmutable('2026-11-12 18:30', $zone));
            }

            $events[] = $event;
        }

        return $events;
    }

    public static function repository(int $count): InMemoryEvents
    {
        $repository = new InMemoryEvents();

        foreach (self::of($count) as $event) {
            $repository->events[$event->id] = $event;
        }

        return $repository;
    }
}
