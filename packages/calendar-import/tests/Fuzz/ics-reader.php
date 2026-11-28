<?php

declare(strict_types=1);

/*
 * The iCalendar reader on texts nobody wrote: a result whose events each have a UID and end at or after they start,
 * one per key, at most as many as the bound, or UnreadableCalendar; never another exception.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CalendarImport\Exception\UnreadableCalendar;
use Phil\CalendarImport\ICalendar\ICalendarReader;
use Phil\CalendarImport\ICalendar\ReaderLimits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$limits = new ReaderLimits(bytes: 8192, events: 50, lineBytes: 1024, depth: 4, components: 200);
$reader = new ICalendarReader($limits);
$zone = new DateTimeZone('America/Toronto');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $zone): void {
    try {
        $result = $reader->read($input, $zone);
    } catch (UnreadableCalendar) {
        return;
    }

    if (count($result->events) > 50) {
        throw new LogicException('More events than the bound.');
    }

    $keys = [];

    foreach ($result->events as $event) {
        if ('' === $event->uid || $event->end < $event->start || isset($keys[$event->key()])) {
            throw new LogicException('An event without a UID, ending before it starts, or twice.');
        }

        $keys[$event->key()] = true;
    }
});
$config->setMaxLen(8192);
