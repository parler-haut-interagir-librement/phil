<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Override;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Throwable;

/**
 * An RSA public key, verifying `RS256` signatures through `web-token/jwt-library`; read from PEM or from a JSON Web Key,
 * its modulus of 2048 to 8192 bits.
 */
final readonly class RsaKey implements VerificationKey
{
    /**
     * The fewest bits of the modulus, as `RsaSigner` signs with.
     */
    public const int MIN_BITS = 2048;

    /**
     * The most bits of the modulus: a larger one, published by whoever runs a site, would only make each verification
     * slower.
     */
    public const int MAX_BITS = 8192;

    private function __construct(
        private JWK $jwk,
    ) {
    }

    /**
     * The key of a public key in PEM — or the public part of a private one —, read by `web-token/jwt-library`.
     *
     * @throws InvalidBadge
     */
    public static function fromPem(string $pem): self
    {
        try {
            $jwk = JWKFactory::createFromKey($pem)->toPublic();
        } catch (Throwable) {
            throw InvalidBadge::because('The public key is not a key in PEM.');
        }

        if ('RSA' !== $jwk->get('kty')) {
            throw InvalidBadge::because('The key is not an RSA key.');
        }

        return self::fromJwk($jwk->all());
    }

    /**
     * The key of a JSON Web Key of type `RSA`: its modulus `n`, of 2048 to 8192 bits, and its public exponent `e`, odd
     * and at least 3, in URL-safe base 64. The members of a private key, when present, are not read.
     *
     * @param array<array-key, mixed> $jwk
     *
     * @throws InvalidBadge
     */
    public static function fromJwk(array $jwk): self
    {
        if ('RSA' !== ($jwk['kty'] ?? null) || !\is_string($jwk['n'] ?? null) || !\is_string($jwk['e'] ?? null)) {
            throw InvalidBadge::because('An RSA JSON Web Key has the type "RSA", its "n" and its "e".');
        }

        if (isset($jwk['alg']) && 'RS256' !== $jwk['alg']) {
            throw InvalidBadge::because('An RSA JSON Web Key verifies RS256 signatures only.');
        }

        $modulus = ltrim(self::decode($jwk['n']), "\x00");
        $exponent = ltrim(self::decode($jwk['e']), "\x00");
        $bits = self::bits($modulus);

        if ($bits < self::MIN_BITS || $bits > self::MAX_BITS) {
            throw InvalidBadge::because(\sprintf('The modulus of an RSA JSON Web Key has %d to %d bits; this one has %d.', self::MIN_BITS, self::MAX_BITS, $bits));
        }

        if ('' === $exponent || \strlen($exponent) > 8 || 0 === (\ord($exponent[-1]) & 1) || "\x01" === $exponent) {
            throw InvalidBadge::because('The exponent of an RSA JSON Web Key is odd, at least 3 and 8 bytes at most.');
        }

        return new self(new JWK(['kty' => 'RSA', 'n' => Base64Url::encode($modulus), 'e' => Base64Url::encode($exponent)]));
    }

    /**
     * The bits of a modulus given as unsigned big-endian bytes without leading zero.
     *
     * @internal
     */
    public static function bits(string $modulus): int
    {
        return '' === $modulus ? 0 : \strlen($modulus) * 8 - (8 - \strlen(decbin(\ord($modulus[0]))));
    }

    /**
     * @internal
     */
    public static function decode(string $text): string
    {
        try {
            return Base64Url::decode($text);
        } catch (InvalidCredential) {
            throw InvalidBadge::because('The parts of an RSA JSON Web Key are in URL-safe base 64.');
        }
    }

    /**
     * The key as a public JSON Web Key: its type, its modulus and its exponent.
     *
     * @return array{kty: 'RSA', n: string, e: string}
     */
    public function toJwk(): array
    {
        /** @var array{kty: 'RSA', n: string, e: string} */
        return $this->jwk->all();
    }

    #[Override]
    public function algorithm(): string
    {
        return 'RS256';
    }

    #[Override]
    public function jwk(): JWK
    {
        return $this->jwk;
    }
}
