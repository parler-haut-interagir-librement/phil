<?php

declare(strict_types=1);

/*
 * The schedule rules on rules nobody wrote: whatever it reads, the parser gives a schedule or refuses with
 * InvalidSchedule, never another error nor a warning; a schedule writes a rule that reads back to it, and its periods
 * over a few weeks follow one another, each asking no more check-ins than it has days.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidSchedule;
use Phil\Habits\Schedule;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$first = Day::fromString('2026-01-29');
$through = $first->plus(40);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($first, $through): void {
    try {
        $schedule = Schedule::parse($input);
    } catch (InvalidSchedule) {
        return;
    }

    $rule = $schedule->toString();

    if (Schedule::parse($rule)->toString() !== $rule || Schedule::parse($rule) !== $schedule) {
        throw new LogicException('A schedule does not read back from its own rule.');
    }

    $next = $first;

    foreach ($schedule->periods($first, $through) as $period) {
        if ($period->first->isBefore($next) || $period->last->isBefore($period->first)) {
            throw new LogicException('Periods overlap or run backwards.');
        }

        if ($period->required < 1 || $period->required > $period->first->daysUntil($period->last) + 1 || $period->span <= 0.0) {
            throw new LogicException('A period asks more check-ins than it has days, or stands for no day.');
        }

        $next = $period->last->plus(1);
    }
});
$config->setMaxLen(80);
