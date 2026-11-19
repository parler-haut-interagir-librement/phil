<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use InvalidArgumentException;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Application\View\Period;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\Calendar as CalendarTemplate;
use Phil\PagedMedia\Template\CalendarMonth;

/**
 * The calendar printed, a month per page, by the calendar template of `phil/paged-media`: every item of the views —
 * events in the reader's locale, the almanac's dates — on each day it covers, a time before those that have one, the
 * month and the weekdays named by ICU in the reader's language.
 */
final readonly class PrintableCalendar
{
    /** The months printed at once, at most. */
    public const int MONTHS = 24;

    public function __construct(
        private Items $items,
        private string $timezone,
        private int $firstWeekday = 1,
    ) {
    }

    /**
     * The months from a first one, in the calendar's time zone, as the template takes them.
     *
     * @param list<string>|null $tags only the items with one of these tags
     *
     * @return list<CalendarMonth>
     */
    public function months(int $year, int $month, int $count = 1, string $locale = 'en', ?array $tags = null): array
    {
        if ($count < 1 || $count > self::MONTHS || $month < 1 || $month > 12 || $year < 1 || $year > 9999) {
            throw new InvalidArgumentException(\sprintf('A printed calendar is 1 to %d months from a month of a year from 1 to 9999.', self::MONTHS));
        }

        $zone = new DateTimeZone($this->timezone);
        $title = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $zone, null, 'LLLL y');
        $time = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $zone);
        $first = new DateTimeImmutable(\sprintf('%04d-%02d-01', $year, $month), $zone);
        $months = [];

        for ($index = 0; $index < $count; ++$index) {
            $day = $first->modify(\sprintf('+%d months', $index));
            $period = Period::of(CalendarView::List, $day, $zone, $this->firstWeekday);
            $events = [];

            foreach ($this->items->of($period, $tags) as $item) {
                $line = $item->allDay ? $item->title : $time->format($item->start).' '.$item->title;

                foreach ($this->days($item, $day) as $dayOfMonth) {
                    $events[$dayOfMonth][] = $item->cancelled ? '× '.$line : $line;
                }
            }

            ksort($events);
            $months[] = new CalendarMonth((int) $day->format('Y'), (int) $day->format('n'), mb_convert_case((string) $title->format($day), \MB_CASE_TITLE), $events);
        }

        return $months;
    }

    /**
     * The months as a PDF document, which the application may still draw on before it renders it.
     *
     * @param list<string>|null $tags
     */
    public function document(int $year, int $month, int $count = 1, string $locale = 'en', ?array $tags = null): Document
    {
        return new CalendarTemplate($this->weekdays($locale), $this->firstWeekday)->render($this->months($year, $month, $count, $locale, $tags));
    }

    /**
     * The names of the seven days, from the first day of the week, in a language.
     *
     * @return list<string>
     */
    public function weekdays(string $locale): array
    {
        $name = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, new DateTimeZone('UTC'), null, 'cccc');
        // 2024-01-01 is a Monday.
        $monday = new DateTimeImmutable('2024-01-01', new DateTimeZone('UTC'));

        return array_map(fn (int $offset): string => mb_convert_case((string) $name->format($monday->modify(\sprintf('+%d days', ($this->firstWeekday - 1 + $offset) % 7))), \MB_CASE_TITLE), range(0, 6));
    }

    /**
     * The days of a month an item covers, by their number in the month.
     *
     * @return list<int>
     */
    private function days(Item $item, DateTimeImmutable $month): array
    {
        $of = $month->format('Y-m');

        if ($item->lastDay()->format('Y-m') < $of || $item->firstDay()->format('Y-m') > $of) {
            return [];
        }

        $first = $item->firstDay()->format('Y-m') < $of ? 1 : (int) $item->firstDay()->format('j');
        $last = $item->lastDay()->format('Y-m') > $of ? (int) $month->format('t') : (int) $item->lastDay()->format('j');

        return range($first, $last);
    }
}
