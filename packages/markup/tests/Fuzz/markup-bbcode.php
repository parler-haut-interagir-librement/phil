<?php

declare(strict_types=1);

/*
 * The BBCode reader on inputs nobody wrote: a reader of a written format never throws, whatever it reads.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Markup\Reader\BBCode\BBCodeReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    new BBCodeReader()->read($input);
});
$config->setMaxLen(4096);
