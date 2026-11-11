<?php

declare(strict_types=1);

/*
 * The reader of RDATE and EXDATE lines on inputs nobody wrote: whatever it reads, it gives a list of dates written back
 * and read again to the same line, or refuses the line with one of its own exceptions, and never throws anything else
 * nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Recurrence\Exception\RecurrenceException;
use Phil\Recurrence\RecurrenceDates;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$zone = new DateTimeZone('America/Toronto');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($zone): void {
    try {
        $written = (string) RecurrenceDates::parse($input, $zone);
    } catch (RecurrenceException) {
        // A line refused whole is an answer.
        return;
    }

    if ($written !== (string) RecurrenceDates::parse($written, $zone)) {
        throw new LogicException(sprintf('"%s" is not read back as it is written.', $written));
    }
});
$config->setMaxLen(4096);
