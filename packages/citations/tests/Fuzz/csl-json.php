<?php

declare(strict_types=1);

/*
 * The reader of CSL-JSON on inputs nobody wrote: whatever it reads, it returns references or refuses the input with InvalidReference,
 * and never throws anything else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Citations\CslJson;
use Phil\Citations\Exception\InvalidReference;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new CslJson();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $reader->decode($input);
    } catch (InvalidReference) {
    }
});
$config->setMaxLen(4096);
