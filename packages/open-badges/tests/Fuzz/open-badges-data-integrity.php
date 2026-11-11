<?php

declare(strict_types=1);

/*
 * The verifier of a credential — a token, or a document read as JSON-LD and canonicalized —, on inputs nobody wrote:
 * whatever it reads, it verifies it or refuses it with an exception of the library, and never anything else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Exception\OpenBadgesException;
use Phil\OpenBadges\Verification\CredentialVerifier;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$verifier = new CredentialVerifier(new DidKeyMethods(), 'did:key:z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($verifier): void {
    try {
        $verifier->verify($input);
    } catch (OpenBadgesException) {
    }
});
$config->setMaxLen(4096);
