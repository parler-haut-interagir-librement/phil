<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\ICalendar\ICalendarWriter;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\EditorialCalendar\Review\Review;
use Phil\EditorialCalendar\Review\ReviewRules;
use Phil\EditorialCalendar\View\CalendarView;
use Phil\Resource\ResourceReference;

/**
 * The calendar is shown on every visit of its page and exported on every poll of a subscribed calendar application: a
 * month, its review and its iCalendar stream over a year of 5,000 items, a fifth of them in 500 batches, must each cost
 * a time that grows with the items, never with the items times the batches.
 */
final readonly class CalendarViewBench
{
    private EditorialCalendar $calendar;

    public function __construct()
    {
        $items = [];
        $batches = [];
        $start = new DateTimeImmutable('2026-01-01Z');

        for ($i = 0; $i < 5_000; ++$i) {
            $items[] = PlannedItem::plan('i'.$i, ResourceReference::of('article', (string) $i), 'Article '.$i, 0 === $i % 3 ? 'en' : 'fr', new AuthorId('u'.($i % 40)), $start->modify(\sprintf('+%d minutes', $i * 105)));
        }

        for ($b = 0; $b < 500; ++$b) {
            $batches[] = new PublicationBatch('b'.$b, 'Batch '.$b, ['i'.($b * 10), 'i'.($b * 10 + 1)], $start->modify(\sprintf('+%d hours', $b * 17)));
        }

        $this->calendar = EditorialCalendar::of($items, $batches);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchMonth(): void
    {
        new CalendarView()->month($this->calendar, new DateTimeImmutable('2026-06-15Z'), new DateTimeZone('America/Toronto'));
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReview(): void
    {
        new Review(new ReviewRules(3_600, 86_400))->issues($this->calendar, new DateTimeImmutable('2026-06-01Z'), new DateTimeImmutable('2026-07-01Z'));
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchICalendar(): void
    {
        new ICalendarWriter('example.org')->write($this->calendar, new DateTimeImmutable('2026-01-01Z'), new DateTimeImmutable('2027-01-01Z'), new DateTimeImmutable('2026-11-13Z'));
    }
}
