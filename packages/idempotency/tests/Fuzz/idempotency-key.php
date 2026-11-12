<?php

declare(strict_types=1);

/*
 * The reader of the Idempotency-Key header, on text nobody wrote: whatever it reads, it returns a key whose quoted form
 * reads back to the same key, or throws one of the library's exceptions, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Idempotency\Exception\IdempotencyException;
use Phil\Idempotency\IdempotencyKey;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $key = IdempotencyKey::fromHeader($input);
    } catch (IdempotencyException) {
        return;
    }

    if (!IdempotencyKey::fromHeader($key->toHeaderValue())->equals($key) || !IdempotencyKey::fromString($key->value)->equals($key)) {
        throw new LogicException('The quoted form of a key does not read back to the same key.');
    }
});
$config->setMaxLen(300);
