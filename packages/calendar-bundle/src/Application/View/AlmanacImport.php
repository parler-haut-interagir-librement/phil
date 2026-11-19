<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Almanac;
use Phil\Almanac\ComputedDate;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;

/**
 * The almanac's dates of a year made events of the calendar, for those an application wants to change, describe or
 * host like any other: each a whole-day event — a phase, a season or an eclipse on its day —, hosted by `almanac:<key>`, made once
 * however many times the year is imported.
 */
final readonly class AlmanacImport
{
    public const string HOST = 'almanac';

    /**
     * @param Almanac|Closure(): Almanac $almanac a closure asks for the almanac at each import, with the dates stored
     *                                            since
     */
    public function __construct(
        private Almanac|Closure $almanac,
        private Calendar $calendar,
        private string $timezone = 'UTC',
    ) {
    }

    /**
     * @param list<string>|null $tags only the dates with one of these tags
     *
     * @return array{int, int} the events made, and the dates that already had theirs
     */
    public function import(int $year, ?array $tags = null): array
    {
        $zone = new DateTimeZone($this->timezone);
        $from = new DateTimeImmutable(\sprintf('%d-01-01', $year), $zone);
        $made = 0;
        $kept = 0;

        $almanac = $this->almanac instanceof Almanac ? $this->almanac : ($this->almanac)();

        foreach ($almanac->between($from, $from->modify('+1 year'), $tags) as $date) {
            // A whole day keeps its date; an instant is moved to the calendar's time zone first.
            $first = new DateTimeImmutable(($date->span->allDay ? $date->span->start : $date->span->start->setTimezone($zone))->format('Y-m-d'), $zone);

            if ([] !== $this->calendar->occurrences($first, $first->modify('+1 day -1 second'), self::HOST, $date->definition->key)) {
                ++$kept;

                continue;
            }

            $event = $this->calendar->schedule($this->draft($date, $first, $zone));
            $this->calendar->host($event, self::HOST, $date->definition->key);
            ++$made;
        }

        return [$made, $kept];
    }

    private function draft(ComputedDate $date, DateTimeImmutable $first, DateTimeZone $zone): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $date->definition->title;
        $draft->start = $first;
        $draft->end = $date->span->allDay ? new DateTimeImmutable($date->span->end->format('Y-m-d'), $zone) : $first;
        $draft->allDay = true;
        $draft->timezone = $zone->getName();
        $draft->tags = $date->definition->tags;

        return $draft;
    }
}
