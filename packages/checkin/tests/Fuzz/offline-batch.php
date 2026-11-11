<?php

declare(strict_types=1);

/*
 * The reader of the journals devices send, on JSON nobody wrote: whatever it reads, it returns scans or refuses the
 * batch with InvalidOfflineBatch, and throws nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Checkin\Exception\InvalidOfflineBatch;
use Phil\Checkin\Offline\OfflineBatch;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        OfflineBatch::read(json_decode($input, true, 8));
    } catch (InvalidOfflineBatch) {
    }
});
$config->setMaxLen(4096);
