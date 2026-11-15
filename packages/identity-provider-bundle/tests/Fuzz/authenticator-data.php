<?php

declare(strict_types=1);

/*
 * The reader of authenticator data, on bytes nobody wrote: it returns the parts or throws MalformedInput, and a
 * credential it returns lies within the bytes given.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AuthenticatorData;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = AuthenticatorData::parse($input);
    } catch (MalformedInput) {
        return;
    }

    if (32 !== strlen($data->rpIdHash) || (null !== $data->credentialId && !str_contains($input, $data->credentialId))) {
        throw new LogicException('The authenticator data returned a part it was not given.');
    }
});
$config->setMaxLen(2048);
