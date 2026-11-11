<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

/**
 * The issuer's private key, which signs the credentials.
 */
interface Signer
{
    /**
     * The JSON Web Algorithm name: `EdDSA`, `RS256`.
     */
    public function algorithm(): string;

    public function sign(string $data): string;

    /**
     * The public key as a JSON Web Key, published by the issuer and put in each token's header.
     *
     * @return array<string, string>
     */
    public function publicJwk(): array;

    /**
     * The public key that verifies what this signer signs.
     */
    public function verificationKey(): VerificationKey;
}
