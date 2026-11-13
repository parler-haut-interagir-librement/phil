<?php

declare(strict_types=1);

/*
 * The reader of link tokens, on text nobody wrote: whatever it reads, it returns the text itself, which decodes to 32
 * bytes that encode back to it, or throws the library's InvalidToken, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\AccessLinks\Exception\InvalidToken;
use Phil\AccessLinks\LinkToken;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $token = LinkToken::parse($input);
    } catch (InvalidToken) {
        return;
    }

    $bytes = base64_decode(strtr($token, '-_', '+/'), true);

    if ($token !== $input || !is_string($bytes) || LinkToken::BYTES !== strlen($bytes) || rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $token) {
        throw new LogicException('A token was read that does not encode 32 bytes exactly.');
    }
});
$config->setMaxLen(100);
