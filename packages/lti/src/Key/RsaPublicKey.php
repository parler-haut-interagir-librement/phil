<?php

declare(strict_types=1);

namespace Phil\Lti\Key;

use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Jwt\Base64Url;
use Throwable;

/**
 * An RSA public key of 2048 to 8192 bits that verifies `RS256` signatures, the algorithm of LTI 1.3, with its key
 * identifier: a JSON Web Key of `web-token/jwt-library`, read from a JSON Web Key or from PEM once its type, its size,
 * its exponent, its algorithm and its use are checked, written back as a JSON Web Key for a key set.
 */
final readonly class RsaPublicKey
{
    public const int MIN_BITS = 2048;

    public const int MAX_BITS = 8192;

    private function __construct(
        public JWK $jwk,
        public ?string $keyId,
    ) {
    }

    /**
     * @throws InvalidToken when the text is not an RSA public key of 2048 to 8192 bits in PEM
     */
    public static function fromPem(string $pem, ?string $keyId = null): self
    {
        try {
            $values = JWKFactory::createFromKey($pem)->all();
        } catch (Throwable $throwable) {
            throw InvalidToken::because('The public key is not a key in PEM.', $throwable);
        }

        return self::fromValues($values, $keyId);
    }

    /**
     * A JSON Web Key of type `RSA`: its modulus `n` and exponent `e` in URL-safe base 64, its `kid` when it has one. A key
     * that names another algorithm than `RS256`, or another use than `sig`, is refused; the members other than these are
     * left out, private ones included.
     *
     * @param array<array-key, mixed> $jwk
     *
     * @throws InvalidToken
     */
    public static function fromJwk(array $jwk): self
    {
        if ('RSA' !== ($jwk['kty'] ?? null) || !\is_string($jwk['n'] ?? null) || !\is_string($jwk['e'] ?? null)) {
            throw InvalidToken::because('An RSA JSON Web Key has the type "RSA", a modulus "n" and an exponent "e".');
        }

        if (isset($jwk['alg']) && 'RS256' !== $jwk['alg']) {
            throw InvalidToken::because('An RSA JSON Web Key of LTI verifies RS256 signatures only.');
        }

        if (isset($jwk['use']) && 'sig' !== $jwk['use']) {
            throw InvalidToken::because('An RSA JSON Web Key of LTI is used for signatures.');
        }

        $kid = $jwk['kid'] ?? null;

        if (null !== $kid && !\is_string($kid)) {
            throw InvalidToken::because('The "kid" of a JSON Web Key is a string.');
        }

        $modulus = ltrim(Base64Url::decode($jwk['n']), "\x00");
        $exponent = ltrim(Base64Url::decode($jwk['e']), "\x00");
        $bits = self::bits($modulus);

        if ($bits < self::MIN_BITS || $bits > self::MAX_BITS) {
            throw InvalidToken::because(\sprintf('The modulus of an RSA key has %d to %d bits, not %d.', self::MIN_BITS, self::MAX_BITS, $bits));
        }

        if ('' === $exponent || \strlen($exponent) > 8 || 0 === (\ord($exponent[-1]) & 1) || "\x01" === $exponent) {
            throw InvalidToken::because('The exponent of an RSA key is odd, at least 3, and 8 bytes at most.');
        }

        $kid = '' === $kid ? null : $kid;
        $values = ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig'];

        if (null !== $kid) {
            $values['kid'] = $kid;
        }

        return new self(new JWK($values + ['n' => Base64Url::encode($modulus), 'e' => Base64Url::encode($exponent)]), $kid);
    }

    /**
     * The public half of the members of a key that `web-token/jwt-library` read, under the identifier given.
     *
     * @internal for `RsaPrivateKey`, which derives its public half
     *
     * @param array<array-key, mixed> $values
     *
     * @throws InvalidToken when the key is not an RSA key of 2048 to 8192 bits
     */
    public static function fromValues(array $values, ?string $keyId): self
    {
        if ('RSA' !== ($values['kty'] ?? null)) {
            throw InvalidToken::because('The key is not an RSA key.');
        }

        return self::fromJwk(['kty' => 'RSA', 'n' => $values['n'] ?? null, 'e' => $values['e'] ?? null, 'kid' => $keyId]);
    }

    /**
     * The key as a JSON Web Key of a key set: `kty`, `alg`, `use`, `kid` when it has one, `n` and `e`.
     *
     * @return array{kty: 'RSA', alg: 'RS256', use: 'sig', kid?: string, n: string, e: string}
     */
    public function toJwk(): array
    {
        /** @var array{kty: 'RSA', alg: 'RS256', use: 'sig', kid?: string, n: string, e: string} */
        return $this->jwk->all();
    }

    /**
     * The thumbprint of RFC 7638, in URL-safe base 64: a key identifier derived from the key alone.
     */
    public function thumbprint(): string
    {
        return $this->jwk->thumbprint('sha256');
    }

    private static function bits(string $modulus): int
    {
        if ('' === $modulus) {
            return 0;
        }

        return \strlen($modulus) * 8 - (8 - \strlen(decbin(\ord($modulus[0]))));
    }
}
