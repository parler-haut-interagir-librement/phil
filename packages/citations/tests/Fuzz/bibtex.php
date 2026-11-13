<?php

declare(strict_types=1);

/*
 * The reader of BibTeX on inputs nobody wrote: whatever it reads, it returns references or refuses the input with InvalidImport,
 * and never throws anything else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Citations\Exception\InvalidImport;
use Phil\Citations\Import\BibTeXReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new BibTeXReader();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $reader->read($input);
    } catch (InvalidImport) {
    }
});
$config->setMaxLen(4096);
