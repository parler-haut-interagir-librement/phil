<?php

declare(strict_types=1);

/*
 * Cue text nobody wrote: its plain text, voices, SRT and HTML are computed without an error, and the HTML carries no tag it did not build.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\TimedText\CueText;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    CueText::plain($input);
    CueText::voices($input);
    CueText::toSrt($input);
    CueText::fromSrt($input);
    CueText::fromPlain($input, $input);

    if (1 === preg_match('/<(?!\/?(?:b|i|u|ruby|rt|span|br)[ >])/', CueText::toHtml($input))) {
        throw new LogicException('The HTML of a cue carries a tag it did not build.');
    }
});
$config->setMaxLen(4096);
