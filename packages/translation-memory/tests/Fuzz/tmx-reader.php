<?php

declare(strict_types=1);

/*
 * The TMX reader on inputs nobody wrote: whatever it reads, it returns translation units, or refuses the input with an
 * InvalidTmx, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\TranslationMemory\Exception\InvalidTmx;
use Phil\TranslationMemory\Tmx\TmxReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new TmxReader();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $reader->read($input);
    } catch (InvalidTmx) {
        // A file refused whole is an answer.
    }
});
$config->setMaxLen(4096);
