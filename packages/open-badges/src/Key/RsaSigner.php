<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Override;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Exception\InvalidBadge;
use SensitiveParameter;
use Throwable;

/**
 * An RSA private key of 2048 to 8192 bits, signing with the `RS256` algorithm of `web-token/jwt-library` — the
 * algorithm every Open Badges 3.0 verifier supports.
 */
final readonly class RsaSigner implements Signer
{
    public const int MIN_BITS = RsaKey::MIN_BITS;

    public const int MAX_BITS = RsaKey::MAX_BITS;

    private function __construct(
        #[SensitiveParameter]
        private JWK $jwk,
        private RsaKey $publicKey,
    ) {
    }

    /**
     * The key of a private key in PEM, encrypted with the passphrase when one is given, read by `web-token/jwt-library`.
     *
     * @throws InvalidBadge
     */
    public static function fromPem(#[SensitiveParameter] string $pem, #[SensitiveParameter] ?string $passphrase = null): self
    {
        try {
            $jwk = JWKFactory::createFromKey($pem, $passphrase);
        } catch (Throwable) {
            throw InvalidBadge::because('The private key is not a key in PEM, or its passphrase is wrong.');
        }

        if ('RSA' !== $jwk->get('kty') || !$jwk->has('d') || !\is_string($jwk->get('n'))) {
            throw InvalidBadge::because('The key is not an RSA private key.');
        }

        $bits = RsaKey::bits(RsaKey::decode($jwk->get('n')));

        if ($bits < self::MIN_BITS || $bits > self::MAX_BITS) {
            throw InvalidBadge::because(\sprintf('An RSA key has %d to %d bits; this one has %d.', self::MIN_BITS, self::MAX_BITS, $bits));
        }

        return new self($jwk, RsaKey::fromJwk($jwk->toPublic()->all()));
    }

    #[Override]
    public function algorithm(): string
    {
        return 'RS256';
    }

    #[Override]
    public function signatureAlgorithm(): RS256
    {
        return new RS256();
    }

    #[Override]
    public function jwk(): JWK
    {
        return $this->jwk;
    }

    /**
     * @return array{kty: 'RSA', n: string, e: string}
     */
    #[Override]
    public function publicJwk(): array
    {
        return $this->publicKey->toJwk();
    }

    #[Override]
    public function verificationKey(): RsaKey
    {
        return $this->publicKey;
    }
}
