<?php

declare(strict_types=1);

/*
 * The reader on responses nobody wrote: whatever it reads, it returns a transcript, written again as WebVTT, or throws
 * an exception of the library.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\TimedText\TranscriptFormat;
use Phil\Transcription\Exception\TranscriptionException;
use Phil\Transcription\Language;
use Phil\Transcription\Reader\VerboseJsonReader;
use Phil\Transcription\TranscriptionLimits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new VerboseJsonReader(new TranscriptionLimits(maxResponseBytes: 65_536, maxSegments: 1_000, maxTextBytes: 4_096));
$language = new Language('fr-CA');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $language): void {
    try {
        TranscriptFormat::WebVtt->writer()->write($reader->read($input, $language)->toTrack());
    } catch (TranscriptionException) {
        // The errors the reader promises for a response it refuses.
    }
});
$config->setMaxLen(8192);
