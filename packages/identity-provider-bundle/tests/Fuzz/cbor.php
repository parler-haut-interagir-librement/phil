<?php

declare(strict_types=1);

/*
 * The CBOR reader, on bytes nobody wrote: it returns a value or throws MalformedInput, and nothing else — no other
 * exception, no error, no allocation beyond the bytes given.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\WebAuthn\Cbor;
use Phil\IdentityProviderBundle\Domain\WebAuthn\CoseKey;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $value = Cbor::decode($input);
    } catch (MalformedInput) {
        return;
    }

    if (is_array($value)) {
        try {
            CoseKey::toPublicKey($value);
        } catch (MalformedInput) {
            return;
        }
    }
});
$config->setMaxLen(2048);
