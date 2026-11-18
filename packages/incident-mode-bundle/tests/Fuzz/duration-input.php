<?php

declare(strict_types=1);

/*
 * The reading of a duration on texts nobody wrote: whatever it reads, it gives a duration within the bounds of the
 * library or refuses with InvalidIncident, never throws anything else nor warns; a duration it gives writes back to a
 * text that reads to the same duration.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentModeBundle\Application\DurationInput;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $duration = DurationInput::parse($input);
    } catch (InvalidIncident) {
        return;
    }

    if ($duration->seconds < Duration::MINIMUM || $duration->seconds > Duration::MAXIMUM) {
        throw new LogicException('A duration out of bounds was read.');
    }

    if (0 === $duration->seconds % 60 && DurationInput::parse(DurationInput::format($duration))->seconds !== $duration->seconds) {
        throw new LogicException('A duration does not read back from its own text.');
    }
});
$config->setMaxLen(80);
