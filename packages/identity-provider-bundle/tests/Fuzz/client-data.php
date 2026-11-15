<?php

declare(strict_types=1);

/*
 * The reader of client data, on text nobody wrote: it returns the ceremony, challenge and origin, or throws
 * MalformedInput; the checks then refuse with VerificationFailed, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\WebAuthn\ClientData;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        ClientData::parse($input)->assert('webauthn.get', 'challenge', ['https://example.com']);
    } catch (MalformedInput|VerificationFailed) {
        return;
    }
});
$config->setMaxLen(4200);
