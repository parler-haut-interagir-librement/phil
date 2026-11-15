<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

use Jose\Component\Core\JWK;

/**
 * An issuer's public key, which verifies its credentials through `web-token/jwt-library`.
 */
interface VerificationKey
{
    /**
     * The JSON Web Algorithm name the key verifies: `EdDSA`, `RS256`.
     */
    public function algorithm(): string;

    /**
     * The public key as a JSON Web Key of `web-token/jwt-library`.
     */
    public function jwk(): JWK;
}
