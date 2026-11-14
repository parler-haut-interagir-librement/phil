<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * Finds the public key a Data Integrity proof or a token names as its verification method: a `did:key`, a key the
 * issuer publishes in its profile or in the DID document of its `did:web`. An implementation decides whether it fetches
 * anything: `DidKeyMethods` never does, `DidWebMethods` reads DID documents through the `DocumentFetcher` it is given.
 */
interface VerificationMethods
{
    /**
     * @throws InvalidCredential when the method is unknown
     */
    public function key(string $verificationMethod): VerificationKey;
}
