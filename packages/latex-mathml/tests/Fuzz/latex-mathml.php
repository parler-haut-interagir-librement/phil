<?php

declare(strict_types=1);

/*
 * The converter of a LaTeX formula on inputs nobody wrote: whatever it reads, it returns MathML or refuses the
 * formula with InvalidLatex, and never throws anything else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\LatexMathMl\Exception\InvalidLatex;
use Phil\LatexMathMl\LatexToMathMl;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$converter = new LatexToMathMl();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($converter): void {
    try {
        $converter->convert($input);
    } catch (InvalidLatex) {
    }
});
$config->setMaxLen(2048);
