<?php

declare(strict_types=1);

/*
 * The reading of a measurement as a form or a feed gives it, `<decimal> <code>`, on bytes nobody wrote: each read
 * returns a measurement or throws a MeasurementException; nothing else escapes, and what is read is written back as
 * text that reads as the same measurement.
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

$registry = UnitRegistry::standard();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($registry): void {
    try {
        $measurement = $registry->parse($input);
    } catch (MeasurementException) {
        return;
    }

    if (0 !== $registry->parse($measurement->toString())->compareTo($measurement)) {
        throw new LogicException('A measurement does not read back as itself.');
    }
});
$config->setMaxLen(256);
