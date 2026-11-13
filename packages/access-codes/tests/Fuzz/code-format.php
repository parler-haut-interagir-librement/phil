<?php

declare(strict_types=1);

/*
 * The reader of typed codes, on text nobody wrote: whatever it reads, it returns a code of the format's length and
 * alphabet that reads back to itself, shown and typed again, or throws the library's InvalidCode, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\AccessCodes\CodeFormat;
use Phil\AccessCodes\Exception\InvalidCode;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$format = new CodeFormat();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($format): void {
    try {
        $code = $format->parse($input);
    } catch (InvalidCode) {
        return;
    }

    if (strlen($code) !== $format->codeLength() || strspn($code, $format->alphabet->symbols) !== strlen($code)) {
        throw new LogicException('A code was read outside the format.');
    }

    if ($format->parse($format->display($code)) !== $code || $format->parse(strtolower($code)) !== $code) {
        throw new LogicException('A code shown or typed in lowercase does not read back to itself.');
    }
});
$config->setMaxLen(300);
