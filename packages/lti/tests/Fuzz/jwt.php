<?php

declare(strict_types=1);

/*
 * The reader of compact tokens and of key sets, on text nobody wrote: `Jwt::parse()` returns a token whose header names
 * an algorithm, or throws InvalidToken; `KeySet::fromJson()` returns a set of RSA keys within its bounds, or throws
 * InvalidToken. Nothing else escapes.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Key\KeySet;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $jwt = Jwt::parse($input);

        if ('' === $jwt->algorithm() || 1 !== substr_count($jwt->signingInput, '.')) {
            throw new LogicException('A token was read without its algorithm or its two signed parts.');
        }
    } catch (InvalidToken) {
    }

    try {
        if (count(KeySet::fromJson($input)->keys) > KeySet::MAX_KEYS) {
            throw new LogicException('A key set holds more keys than its bound.');
        }
    } catch (InvalidToken) {
    }
});
$config->setMaxLen(8192);
