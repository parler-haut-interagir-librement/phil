<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\EdDSA;
use Override;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;
use SensitiveParameter;

/**
 * An Ed25519 private key, signing with the `EdDSA` algorithm of `web-token/jwt-library`: the key of JSON Web Tokens and
 * of `eddsa-rdfc-2022` Data Integrity proofs.
 */
final readonly class Ed25519Signer implements Signer
{
    /**
     * The length of an Ed25519 seed, in bytes.
     */
    public const int SEED_BYTES = 32;

    private function __construct(
        #[SensitiveParameter]
        private JWK $jwk,
    ) {
    }

    /**
     * The key pair derived from a seed of 32 bytes, kept by the application as a secret.
     */
    public static function fromSeed(#[SensitiveParameter] string $seed): self
    {
        if (self::SEED_BYTES !== \strlen($seed)) {
            throw InvalidBadge::because(\sprintf('An Ed25519 seed is %d bytes; this one is %d.', self::SEED_BYTES, \strlen($seed)));
        }

        return new self(new JWK([
            'kty' => 'OKP',
            'crv' => 'Ed25519',
            'x' => Base64Url::encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed))),
            'd' => Base64Url::encode($seed),
        ]));
    }

    #[Override]
    public function algorithm(): string
    {
        return 'EdDSA';
    }

    #[Override]
    public function signatureAlgorithm(): EdDSA
    {
        return new EdDSA();
    }

    #[Override]
    public function jwk(): JWK
    {
        return $this->jwk;
    }

    /**
     * @return array{kty: 'OKP', crv: 'Ed25519', x: string}
     */
    #[Override]
    public function publicJwk(): array
    {
        return $this->verificationKey()->toJwk();
    }

    #[Override]
    public function verificationKey(): Ed25519Key
    {
        return Ed25519Key::fromJwk($this->jwk->toPublic()->all());
    }
}
