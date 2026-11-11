<?php

declare(strict_types=1);

/*
 * The reader of a range of the Pwned Passwords API on inputs nobody wrote: whatever it reads, it returns occurrences
 * or throws BreachCheckUnavailable, and never anything else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Passwords\Breach\RangeResponse;
use Phil\Passwords\Exception\BreachCheckUnavailable;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        RangeResponse::occurrences($input, '1E4C9B93F3F0682250B6CF8331B7EE68FD8');
    } catch (BreachCheckUnavailable) {
    }
});
$config->setMaxLen(4096);
