<?php

declare(strict_types=1);

/*
 * The reader on documents nobody wrote: whatever it reads, it returns what it could read, or throws an exception of the
 * library.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Audio\Exception\AudioException;
use Phil\Audio\Ffmpeg\FfprobeReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new FfprobeReader(65_536);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $reader->read($input)->tag('title');
    } catch (AudioException) {
        // The errors the reader promises for a document it refuses.
    }
});
$config->setMaxLen(8192);
