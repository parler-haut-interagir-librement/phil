<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * A key that signs the provider's tokens: a `PrivateKey` held in memory, or a key that never leaves a hardware module
 * or a key management service, whose adapter sends it the bytes to sign.
 */
interface Signer
{
    public SigningAlgorithm $algorithm { get; }

    /** The key identifier, the `kid` of the header and of the key set. */
    public string $kid { get; }

    /**
     * The signature of the JWS signing input — the encoded header, a dot and the encoded payload — as JWA writes it
     * (RFC 7518): the RSASSA-PKCS1-v1_5 bytes for RS256, `r` and `s` of 32 bytes each for ES256, the 64 bytes of
     * Ed25519 for EdDSA.
     */
    public function sign(string $input): string;

    /**
     * The JSON Web Key of the key set: its type, use, algorithm, identifier and public members only.
     *
     * @return array<string, string>
     */
    public function publicJwk(): array;
}
