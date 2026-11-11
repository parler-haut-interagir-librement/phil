<?php

declare(strict_types=1);

/*
 * The tokenizers on inputs nobody wrote: a text is either cut into tokens that give it back byte for byte, or refused
 * with an exception of the library. Two texts are then compared word by word and as HTML.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\TextDiff\Exception\TextDiffException;
use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Tokenizer;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$differ = new TextDiff();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($differ): void {
    try {
        foreach (Tokenizer::cases() as $tokenizer) {
            if (implode('', $tokenizer->tokens($input)) !== $input) {
                throw new LogicException(sprintf('The %s tokens do not give the text back.', $tokenizer->value));
            }
        }

        $half = intdiv(strlen($input), 2);
        $differ->toHtml($differ->words(substr($input, 0, $half), substr($input, $half)));
        $differ->toHtml($differ->html(substr($input, $half), substr($input, 0, $half)));
    } catch (TextDiffException) {
        // The failure the library promises for a text that is not UTF-8.
    }
});
$config->setMaxLen(4096);
