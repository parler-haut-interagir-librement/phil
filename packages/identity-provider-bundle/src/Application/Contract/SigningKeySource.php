<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

use Jose\Component\Core\JWK;

/**
 * The port of the keys the provider signs its tokens with and publishes: the database of the bundle, rotated on a
 * schedule and sealed by the key management service (`SigningKeys`); key files (`FileSigningKeys`); or a hardware
 * security module or a key management service that signs, through an adapter of the application.
 *
 * A source keeps the keys it loaded instead of loading them for each signature, and gives the same answers to every
 * server of the provider.
 */
interface SigningKeySource
{
    /**
     * The key that signs now.
     */
    public function active(): Signer;

    /**
     * The JSON Web Key Set: the key that signs, the keys about to sign, and the keys that signed tokens still valid.
     *
     * @return array{keys: list<array<string, string>>}
     */
    public function jwks(): array;

    /**
     * The published JSON Web Key of a key identifier, null when the source does not publish it.
     */
    public function publicKey(string $kid): ?JWK;
}
