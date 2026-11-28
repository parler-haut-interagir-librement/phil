<?php

declare(strict_types=1);

/*
 * The reader of the format `phil-shifts/1` on documents nobody wrote: `ShiftPlan::fromJson()`, then a roster of each
 * shift read back from its places. Each returns or throws a ShiftsException; nothing else escapes, and a plan read back
 * writes and reads the same.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Shifts\Exception\ShiftsException;
use Phil\Shifts\Format\RosterFormat;
use Phil\Shifts\Format\ShiftPlan;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $plan = ShiftPlan::fromJson($input);
    } catch (ShiftsException) {
        return;
    }

    if ($plan->toJson() !== ShiftPlan::fromJson($plan->toJson())->toJson()) {
        throw new LogicException('A plan read back does not write the same.');
    }

    $places = json_decode($input, true, 16);

    foreach ($plan->shifts as $shift) {
        try {
            RosterFormat::fromArray($shift, is_array($places) ? ($places['places'] ?? []) : []);
        } catch (ShiftsException) {
            continue;
        }
    }
});
$config->setMaxLen(4096);
