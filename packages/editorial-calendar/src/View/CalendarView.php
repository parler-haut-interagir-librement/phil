<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\View;

use DateTimeImmutable;
use DateTimeZone;
use Phil\EditorialCalendar\Contract\TransitionReader;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\Filter;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\Moment;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\EditorialCalendar\Transition\NoTransitions;

/**
 * The views of a calendar: a day, a week or a month in a time zone, cut into its days, and a list between two moments.
 * Each shows the items planned alone, each batch as one entry with all its items, and the transitions its reader
 * finds; withdrawn items are left out. A filter keeps the items of some authors, sections or states, and the batches
 * holding one of them; a filter that is not empty leaves out the transitions, which have none of these. It keeps no
 * state between calls.
 */
final readonly class CalendarView
{
    public function __construct(
        private TransitionReader $transitions = new NoTransitions(),
    ) {
    }

    /**
     * The day of `$date` in `$zone`.
     */
    public function day(EditorialCalendar $calendar, DateTimeImmutable $date, DateTimeZone $zone, ?Filter $filter = null): Period
    {
        return $this->days($calendar, $date->setTimezone($zone)->setTime(0, 0), 1, $filter ?? Filter::all());
    }

    /**
     * The week of `$date` in `$zone`, starting on `$firstDay`: 1 for Monday, as ISO 8601 numbers them, to 7 for Sunday.
     *
     * @throws InvalidItem when `$firstDay` is not 1 to 7
     */
    public function week(EditorialCalendar $calendar, DateTimeImmutable $date, DateTimeZone $zone, int $firstDay = 1, ?Filter $filter = null): Period
    {
        if ($firstDay < 1 || $firstDay > 7) {
            throw InvalidItem::because(\sprintf('A week starts on a day numbered 1 for Monday to 7 for Sunday; %d is not one.', $firstDay));
        }

        $local = $date->setTimezone($zone)->setTime(0, 0);
        $back = ((int) $local->format('N') - $firstDay + 7) % 7;

        return $this->days($calendar, $local->modify(\sprintf('-%d days', $back)), 7, $filter ?? Filter::all());
    }

    /**
     * The month of `$date` in `$zone`.
     */
    public function month(EditorialCalendar $calendar, DateTimeImmutable $date, DateTimeZone $zone, ?Filter $filter = null): Period
    {
        $local = $date->setTimezone($zone)->setTime(0, 0);

        return $this->days($calendar, $local->modify('first day of this month'), (int) $local->format('t'), $filter ?? Filter::all());
    }

    /**
     * The entries from `$from`, included, to `$to`, excluded, without days.
     *
     * @throws InvalidItem when `$to` is not after `$from`
     */
    public function list(EditorialCalendar $calendar, DateTimeImmutable $from, DateTimeImmutable $to, ?Filter $filter = null): Period
    {
        if ($to <= $from) {
            throw InvalidItem::because('A list ends after it starts.');
        }

        return new Period(Moment::utc($from), Moment::utc($to), $this->entries($calendar, Moment::utc($from), Moment::utc($to), $filter ?? Filter::all()));
    }

    private function days(EditorialCalendar $calendar, DateTimeImmutable $first, int $count, Filter $filter): Period
    {
        $from = Moment::utc($first);
        $to = Moment::utc($first->modify(\sprintf('+%d days', $count)));
        $entries = $this->entries($calendar, $from, $to, $filter);
        $days = [];

        for ($i = 0; $i < $count; ++$i) {
            $start = $first->modify(\sprintf('+%d days', $i));
            $dayFrom = Moment::utc($start);
            $dayTo = Moment::utc($start->modify('+1 day'));
            $days[] = new Day(
                $start->format('Y-m-d'),
                $dayFrom,
                $dayTo,
                array_values(array_filter($entries, static fn (Entry $entry): bool => Moment::within($entry->at, $dayFrom, $dayTo))),
            );
        }

        return new Period($from, $to, $entries, $days);
    }

    /**
     * @return list<Entry>
     */
    private function entries(EditorialCalendar $calendar, DateTimeImmutable $from, DateTimeImmutable $to, Filter $filter): array
    {
        $entries = [];

        foreach ($calendar->items() as $item) {
            if (ItemState::Withdrawn !== $item->state && $filter->accepts($item) && null !== $item->publishAt && !$calendar->batchOf($item->id) instanceof PublicationBatch && Moment::within($item->publishAt, $from, $to)) {
                $entries[] = Entry::item($item, $item->publishAt);
            }
        }

        foreach ($calendar->batches() as $batch) {
            $items = array_values(array_filter(array_map($calendar->item(...), $batch->items), static fn (PlannedItem $item): bool => ItemState::Withdrawn !== $item->state && $filter->accepts($item)));

            if (null !== $batch->at && [] !== $items && Moment::within($batch->at, $from, $to)) {
                $entries[] = Entry::batch($batch, $batch->at, $items);
            }
        }

        foreach ($filter->isEmpty() ? $this->transitions->between($from, $to) : [] as $transition) {
            if (Moment::within($transition->at, $from, $to)) {
                $entries[] = Entry::transition($transition);
            }
        }

        usort($entries, Entry::compare(...));

        return $entries;
    }
}
