<?php

declare(strict_types=1);

/*
 * The export of design tokens on stylesheets nobody wrote: it gives a file the JSON encodes, each property either a
 * token or left out, or refuses an OKLCH colour it cannot read with an InvalidColor, and never throws anything else
 * nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\UxCss\Exception\InvalidColor;
use Phil\UxCss\Token\DesignTokens;
use Phil\UxCss\Token\TokenSheet;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $sheet = TokenSheet::fromCss($input);

    try {
        $tokens = DesignTokens::fromSheet($sheet);
    } catch (InvalidColor) {
        // A colour refused is an answer.
        return;
    }

    $count = 0;

    foreach ($tokens->toArray() as $key => $group) {
        $count += '$description' === $key || '$extensions' === $key || !is_array($group) ? 0 : count($group);
    }

    if ($count + count($tokens->skipped()) !== count($sheet->names())) {
        throw new LogicException('A property is neither a token nor left out.');
    }

    try {
        $tokens->toJson();
    } catch (JsonException) {
        // Invalid UTF-8 in a value written as it is: the stylesheet of a site is UTF-8.
        if (1 === preg_match('//u', $input)) {
            throw new LogicException('Tokens of a UTF-8 stylesheet do not encode as JSON.');
        }
    }
});
$config->setMaxLen(4096);
