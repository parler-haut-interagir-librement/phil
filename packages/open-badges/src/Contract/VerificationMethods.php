<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * Finds the public key a Data Integrity proof names as its `verificationMethod`: a `did:key`, or a key the issuer
 * publishes in its profile. An implementation decides whether it fetches anything; the library's own never do.
 */
interface VerificationMethods
{
    /**
     * @throws InvalidCredential when the method is unknown
     */
    public function key(string $verificationMethod): VerificationKey;
}
