<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use Override;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Exception\InvalidBadge;
use SensitiveParameter;

/**
 * An Ed25519 private key, signing with the `EdDSA` algorithm through libsodium: the key of JSON Web Tokens and of
 * `eddsa-rdfc-2022` Data Integrity proofs.
 */
final readonly class Ed25519Signer implements Signer
{
    /**
     * @var non-empty-string
     */
    private string $secretKey;

    private function __construct(#[SensitiveParameter] string $secretKey)
    {
        if ('' === $secretKey) {
            throw InvalidBadge::because('An Ed25519 secret key is not empty.');
        }

        $this->secretKey = $secretKey;
    }

    /**
     * The key pair derived from a seed of 32 bytes, kept by the application as a secret.
     */
    public static function fromSeed(#[SensitiveParameter] string $seed): self
    {
        if (\SODIUM_CRYPTO_SIGN_SEEDBYTES !== \strlen($seed)) {
            throw InvalidBadge::because(\sprintf('An Ed25519 seed is %d bytes; this one is %d.', \SODIUM_CRYPTO_SIGN_SEEDBYTES, \strlen($seed)));
        }

        return new self(sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed)));
    }

    #[Override]
    public function algorithm(): string
    {
        return 'EdDSA';
    }

    #[Override]
    public function sign(string $data): string
    {
        return sodium_crypto_sign_detached($data, $this->secretKey);
    }

    #[Override]
    public function publicJwk(): array
    {
        return $this->publicKey()->toJwk();
    }

    #[Override]
    public function verificationKey(): Ed25519Key
    {
        return $this->publicKey();
    }

    private function publicKey(): Ed25519Key
    {
        return new Ed25519Key(sodium_crypto_sign_publickey_from_secretkey($this->secretKey));
    }
}
