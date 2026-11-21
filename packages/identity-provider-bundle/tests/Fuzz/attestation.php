<?php

declare(strict_types=1);

/*
 * The reader of attestation statements, on CBOR nobody wrote, beside the authenticator data of a known passkey: it
 * returns a type of attestation or throws MalformedInput or VerificationFailed, and it never calls a statement it
 * could not verify trusted.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationStatement;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationType;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AuthenticatorData;
use Phil\IdentityProviderBundle\Domain\WebAuthn\Cbor;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

// The authenticator data of a passkey on P-256, its credential attested: one AAGUID, one identifier, one COSE key.
$coseKey = "\xA5\x01\x02\x03\x26\x20\x01\x21\x58\x20".str_repeat("\x01", 32)."\x22\x58\x20".str_repeat("\x02", 32);
$authData = hash('sha256', 'example.com', true)."\x45\x00\x00\x00\x00".str_repeat("\x03", 16)."\x00\x04abcd".$coseKey;
$data = AuthenticatorData::parse($authData);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($authData, $data): void {
    try {
        $statement = Cbor::decode($input);
        $type = AttestationStatement::verify('packed', $statement, $authData, str_repeat("\0", 32), $data, [], 1_794_700_000);
        $none = AttestationStatement::verify('none', $statement, $authData, str_repeat("\0", 32), $data, [], 1_794_700_000);
    } catch (MalformedInput|VerificationFailed) {
        return;
    }

    if (AttestationType::Trusted === $type || AttestationType::None !== $none) {
        throw new LogicException('A statement was trusted without a trusted root, or a statement of none was not empty.');
    }
});
$config->setMaxLen(4096);
