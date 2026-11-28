<?php

declare(strict_types=1);

/*
 * Unit definitions from a configuration nobody checked, decoded from JSON: each set builds a registry or throws a
 * MeasurementException; nothing else escapes, and every unit built converts one of itself to its standard unit and
 * back.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\UnitRegistry;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $definitions = json_decode($input, true, 8);

    if (!is_array($definitions)) {
        return;
    }

    try {
        $registry = UnitRegistry::fromDefinitions($definitions);

        foreach ($registry->families() as $family) {
            foreach ($family->units as $unit) {
                $one = $registry->measurement('1', $unit->code);

                if (0 !== $one->convertTo($family->standard)->convertTo($unit)->compareTo($one)) {
                    throw new LogicException('A unit does not come back from its standard unit.');
                }
            }
        }
    } catch (MeasurementException) {
        return;
    }
});
$config->setMaxLen(4096);
