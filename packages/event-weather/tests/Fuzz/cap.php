<?php

declare(strict_types=1);

/*
 * The CAP reader on documents nobody wrote: whatever it reads, it returns alerts or throws InvalidCapDocument.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\EventWeather\Cap\CapReader;
use Phil\EventWeather\Exception\InvalidCapDocument;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new CapReader(maxBytes: 65_536);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $reader->read($input);
    } catch (InvalidCapDocument) {
        // The error the reader promises for a document it refuses.
    }
});
$config->setMaxLen(8192);
