<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Contract;

use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;

/**
 * The issuer's private key, which signs the credentials through `web-token/jwt-library`: the key as a JSON Web Key and
 * the signature algorithm of that library that signs with it.
 */
interface Signer
{
    /**
     * The JSON Web Algorithm name, the name of `signatureAlgorithm()`: `EdDSA`, `RS256`.
     */
    public function algorithm(): string;

    /**
     * The algorithm of `web-token/jwt-library` that signs with `jwk()`.
     */
    public function signatureAlgorithm(): SignatureAlgorithm;

    /**
     * The key `signatureAlgorithm()` signs with: the private JSON Web Key of a key held in memory.
     */
    public function jwk(): JWK;

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
