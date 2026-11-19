<?php

declare(strict_types=1);

/*
 * The reader of confirmation tokens on text nobody wrote: whatever it reads, a token is read or refused with
 * InvalidToken, never another error nor a warning; a token read writes back to the same text, is 66 characters, and
 * names a request the library would issue.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\GuardianConsent\ConfirmationToken;
use Phil\GuardianConsent\Exception\InvalidToken;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $token = ConfirmationToken::parse($input);
    } catch (InvalidToken) {
        return;
    }

    if ($token->toString() !== $input || ConfirmationToken::LENGTH !== strlen($input)) {
        throw new LogicException('A token does not write back to the text it was read from.');
    }

    if (!ConfirmationToken::isRequestId($token->requestId)) {
        throw new LogicException('A token names a request the library would not issue.');
    }
});
$config->setMaxLen(128);
