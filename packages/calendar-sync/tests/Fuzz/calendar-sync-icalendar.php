<?php

declare(strict_types=1);

/*
 * The iCalendar reader on texts nobody wrote: busy periods inside the window, each ending after it starts, at most
 * BusyPeriods::MAX, or InvalidCalendarData; never another exception.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CalendarSync\BusyPeriods;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\ICalendar\ICalendarReader;
use Phil\CalendarSync\SyncWindow;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$window = SyncWindow::days(new DateTimeImmutable('2026-11-01T00:00:00Z'), 60);
$zone = new DateTimeZone('America/Toronto');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($window, $zone): void {
    try {
        $periods = ICalendarReader::busy($input, $window, $zone);
    } catch (InvalidCalendarData) {
        return;
    }

    if (count($periods) > BusyPeriods::MAX) {
        throw new LogicException('More periods than the most.');
    }

    foreach ($periods as $period) {
        if ($period->end <= $period->start || $period->end <= $window->from || $period->start >= $window->to) {
            throw new LogicException('A period outside the window, or without length.');
        }
    }
});
$config->setMaxLen(8192);
