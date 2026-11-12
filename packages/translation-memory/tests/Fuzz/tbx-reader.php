<?php

declare(strict_types=1);

/*
 * The TBX reader on inputs nobody wrote: whatever it reads, it returns a glossary, or refuses the input with an
 * InvalidTbx, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\TranslationMemory\Exception\InvalidTbx;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Tbx\TbxReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new TbxReader();
$source = LanguageTag::of('en-CA');
$target = LanguageTag::of('fr-CA');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $source, $target): void {
    try {
        $reader->read($input, $source, $target);
    } catch (InvalidTbx) {
        // A file refused whole is an answer.
    }
});
$config->setMaxLen(4096);
