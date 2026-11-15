<?php

declare(strict_types=1);

/*
 * The JSON chapters reader on documents nobody wrote: whatever it reads, it returns chapters, written again, or throws an exception of the library.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\TimedText\Exception\TimedTextException;
use Phil\TimedText\Limits;
use Phil\TimedText\Podcast\JsonChapterReader;
use Phil\TimedText\Podcast\JsonChapterWriter;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new JsonChapterReader(new Limits(maxBytes: 65_536, maxCues: 1_000, maxTextBytes: 4_096));
$writer = new JsonChapterWriter();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $writer): void {
    try {
        $writer->write($reader->read($input));
    } catch (TimedTextException) {
        // The errors the reader promises for a document it refuses.
    }
});
$config->setMaxLen(8192);
