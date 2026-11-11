<?php

declare(strict_types=1);

/*
 * The reader of line ranges on inputs nobody wrote: whatever it reads, it returns ranges that write back to a text it
 * reads again as the same ranges, or refuses the input with an InvalidLineRanges, and never throws anything else nor
 * warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Highlight\Exception\InvalidLineRanges;
use Phil\Highlight\LineRanges;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $ranges = LineRanges::parse($input);
    } catch (InvalidLineRanges) {
        // Ranges refused whole are an answer.
        return;
    }

    if (LineRanges::parse($ranges->toString())->toArray() !== $ranges->toArray()) {
        throw new LogicException('Line ranges read do not write back to themselves.');
    }
});
$config->setMaxLen(4096);
