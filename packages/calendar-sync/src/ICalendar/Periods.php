<?php

declare(strict_types=1);

namespace Phil\CalendarSync\ICalendar;

use DateTimeImmutable;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\BusyPeriods;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\SyncWindow;

/**
 * The periods found, those that overlap the window, at most `BusyPeriods::MAX`.
 *
 * @internal
 */
final class Periods
{
    /** @var list<BusyPeriod> */
    private array $periods = [];

    public function __construct(
        public readonly SyncWindow $window,
    ) {
    }

    public function add(DateTimeImmutable $start, DateTimeImmutable $end, bool $tentative): void
    {
        if ($end <= $start || $end <= $this->window->from || $start >= $this->window->to) {
            return;
        }

        if (\count($this->periods) >= BusyPeriods::MAX) {
            throw InvalidCalendarData::because(\sprintf('A calendar gives %d busy periods in a window at most.', BusyPeriods::MAX));
        }

        $this->periods[] = new BusyPeriod($start, $end, $tentative);
    }

    /**
     * @return list<BusyPeriod>
     */
    public function all(): array
    {
        return $this->periods;
    }
}
