<?php

declare(strict_types=1);

/*
 * The parser of a SKU pattern on inputs nobody wrote: whatever it reads, it returns a pattern or refuses it with
 * `InvalidDefinition`, and a pattern it returns renders.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\SkuPattern;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $pattern = SkuPattern::parse($input);
    } catch (InvalidDefinition) {
        return;
    }

    $pattern->render(array_fill_keys($pattern->placeholders(), 'X'));
});
$config->setMaxLen(256);
