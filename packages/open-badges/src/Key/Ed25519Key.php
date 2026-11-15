<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use Jose\Component\Core\JWK;
use Override;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Encoding\Multibase;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * An Ed25519 public key, verifying `EdDSA` signatures through `web-token/jwt-library`; read from a JSON Web Key or from
 * the `publicKeyMultibase` of a Multikey.
 */
final readonly class Ed25519Key implements VerificationKey
{
    /**
     * The multicodec prefix of an Ed25519 public key, `0xed01`, in a Multikey.
     */
    public const string MULTICODEC = "\xed\x01";

    /**
     * The length of an Ed25519 public key, in bytes.
     */
    public const int BYTES = 32;

    /**
     * @var non-empty-string
     */
    public string $publicKey;

    public function __construct(string $publicKey)
    {
        if (self::BYTES !== \strlen($publicKey)) {
            throw InvalidBadge::because(\sprintf('An Ed25519 public key is %d bytes; this one is %d.', self::BYTES, \strlen($publicKey)));
        }

        $this->publicKey = $publicKey;
    }

    /**
     * The key of a JSON Web Key of type `OKP` on the curve `Ed25519`.
     *
     * @param array<array-key, mixed> $jwk
     */
    public static function fromJwk(array $jwk): self
    {
        if ('OKP' !== ($jwk['kty'] ?? null) || 'Ed25519' !== ($jwk['crv'] ?? null) || !\is_string($jwk['x'] ?? null)) {
            throw InvalidBadge::because('An Ed25519 JSON Web Key has the type "OKP", the curve "Ed25519" and its "x".');
        }

        return new self(Base64Url::decode($jwk['x']));
    }

    /**
     * The key of a Multikey's `publicKeyMultibase`: `z`, then the base 58 of `0xed01` and the 32 bytes of the key.
     *
     * @throws InvalidCredential
     */
    public static function fromMultibase(string $publicKeyMultibase): self
    {
        if (!str_starts_with($publicKeyMultibase, 'z')) {
            throw InvalidCredential::because('An Ed25519 Multikey is encoded in base 58, starting with "z".');
        }

        $bytes = Multibase::decode($publicKeyMultibase);

        if (!str_starts_with($bytes, self::MULTICODEC) || 34 !== \strlen($bytes)) {
            throw InvalidCredential::because('An Ed25519 Multikey is the prefix 0xed01 and 32 bytes.');
        }

        return new self(substr($bytes, 2));
    }

    /**
     * The key as the `publicKeyMultibase` of a Multikey, and as the identifier of a `did:key`.
     */
    public function toMultibase(): string
    {
        return Multibase::base58(self::MULTICODEC.$this->publicKey);
    }

    /**
     * The key as a JSON Web Key.
     *
     * @return array{kty: 'OKP', crv: 'Ed25519', x: string}
     */
    public function toJwk(): array
    {
        return ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => Base64Url::encode($this->publicKey)];
    }

    #[Override]
    public function algorithm(): string
    {
        return 'EdDSA';
    }

    #[Override]
    public function jwk(): JWK
    {
        return new JWK($this->toJwk());
    }
}
