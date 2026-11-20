<?php

declare(strict_types=1);

/*
 * The reader of JSON Web Tokens, on text nobody wrote, against one known key: it throws MalformedInput or
 * VerificationFailed, and never returns claims without a valid signature. The reader of the unverified claims of a
 * client assertion throws MalformedInput or returns an object, under the same bounds.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Jose\Component\Core\JWK;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$key = new JWK(PrivateKey::generate(SigningAlgorithm::EdDSA, 'fuzz')->publicJwk());

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($key): void {
    try {
        Jws::unverifiedClaims($input);
    } catch (MalformedInput) {
    }

    try {
        Jws::decode($input, static fn (string $kid): ?JWK => 'fuzz' === $kid ? $key : null);
    } catch (MalformedInput|VerificationFailed) {
        return;
    }

    throw new LogicException('A token nobody signed was accepted.');
});
$config->setMaxLen(1024);
