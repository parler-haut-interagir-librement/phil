<?php

declare(strict_types=1);

/*
 * The days on text nobody wrote: whatever it reads, a day is read or refused with InvalidDay, never another error nor
 * a warning; a day read writes back to the same text, and its week and month hold it.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidDay;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $day = Day::fromString($input);
    } catch (InvalidDay) {
        return;
    }

    if ($day->toString() !== $input || Day::fromNumber($day->number)->toString() !== $input) {
        throw new LogicException('A day does not write back to the text it was read from.');
    }

    if ($day->weekStart()->isAfter($day) || $day->weekStart()->daysUntil($day) > 6 || 1 !== $day->weekStart()->weekday()) {
        throw new LogicException('A day is not in its own week.');
    }

    if ($day->monthStart()->isAfter($day) || $day->monthEnd()->isBefore($day)) {
        throw new LogicException('A day is not in its own month.');
    }
});
$config->setMaxLen(20);
