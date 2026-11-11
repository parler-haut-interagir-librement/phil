<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

/**
 * An issuer's public key, which verifies its credentials.
 */
interface VerificationKey
{
    /**
     * The JSON Web Algorithm name: `EdDSA`, `RS256`.
     */
    public function algorithm(): string;

    public function verify(string $data, string $signature): bool;
}
