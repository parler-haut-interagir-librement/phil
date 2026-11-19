<?php

declare(strict_types=1);

/*
 * The reader on documents nobody wrote, under tight budgets of notes, regions and style blocks: whatever it reads, it
 * returns what it could read, written again, or throws an exception of the library.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\TimedText\Exception\TimedTextException;
use Phil\TimedText\Limits;
use Phil\TimedText\TranscriptFormat;
use Phil\TimedText\WebVtt\WebVttReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new WebVttReader(new Limits(maxBytes: 65_536, maxCues: 1_000, maxTextBytes: 4_096));
$writer = TranscriptFormat::WebVtt->writer();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $writer): void {
    try {
        $writer->write($reader->inspect($input)->track);
    } catch (TimedTextException) {
        // The errors the reader promises for a document it refuses.
    }
});
$config->setMaxLen(8192);
