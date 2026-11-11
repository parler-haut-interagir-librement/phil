<?php

declare(strict_types=1);

/*
 * The lines of a block, its annotations read, on inputs nobody wrote: whatever the code, one `hl-line` per line, and
 * without annotations the text of the HTML is the code; it never throws nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Highlight\Highlighter;
use Phil\Highlight\LineOptions;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$highlighter = Highlighter::default();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($highlighter): void {
    $lines = substr_count($input, "\n") + (str_ends_with($input, "\n") ? 0 : 1);

    foreach (['php', 'twig', 'bash', 'sql', 'css'] as $language) {
        $annotated = $highlighter->toHtml($input, $language, LineOptions::numbered()->withAnnotations());

        if (substr_count($annotated, '<span class="hl-line') !== $lines) {
            throw new LogicException('Annotations changed the number of lines.');
        }

        if (1 !== preg_match('//u', $input)) {
            continue;
        }

        $plain = $highlighter->toHtml($input, $language, new LineOptions());

        if (html_entity_decode(strip_tags($plain), \ENT_QUOTES | \ENT_HTML5, 'UTF-8') !== $input) {
            throw new LogicException('The lines lost a byte of the code.');
        }
    }
});
$config->setMaxLen(4096);
