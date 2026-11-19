<?php

declare(strict_types=1);

/*
 * The Markdown reader on inputs nobody wrote, under a tight budget of lines: a reader of a written format never throws,
 * whatever it reads, but for a source holding more lines than its budget, which it always refuses.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Markup\Exception\InputTooComplex;
use Phil\Markup\Reader\Markdown\MarkdownReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new MarkdownReader(maxLines: 64);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    $lines = preg_match_all('/\r\n|\r|\n/', $input) + 1;

    try {
        $reader->read($input);
    } catch (InputTooComplex $inputTooComplex) {
        if ($lines <= 64) {
            throw new LogicException(sprintf('A source of %d lines was refused under a budget of 64.', $lines), 0, $inputTooComplex);
        }

        return;
    }

    if ($lines > 64) {
        throw new LogicException(sprintf('A source of %d lines was read under a budget of 64.', $lines));
    }
});
$config->setMaxLen(4096);
