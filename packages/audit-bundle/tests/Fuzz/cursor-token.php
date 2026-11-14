<?php

declare(strict_types=1);

/*
 * The page cursor on tokens nobody wrote: whatever it reads, it gives back the very token it read, or refuses it with
 * an InvalidValue, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\AuditBundle\Domain\Read\Cursor;
use Phil\DddBundle\Domain\Exception\InvalidValue;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $cursor = Cursor::fromToken($input);
    } catch (InvalidValue) {
        // A token refused whole is an answer.
        return;
    }

    if ($cursor->token() !== $input) {
        throw new LogicException('A cursor read does not write back to its token.');
    }
});
$config->setMaxLen(256);
