<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use OpenSSLAsymmetricKey;
use Override;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * An RSA public key, verifying `RS256` signatures through OpenSSL; read from PEM or from a JSON Web Key.
 */
final readonly class RsaKey implements VerificationKey
{
    /**
     * The fewest bits of the modulus of a JSON Web Key, as `RsaSigner` signs with.
     */
    public const int MIN_BITS = 2048;

    /**
     * The most bits of the modulus of a JSON Web Key: a larger one, published by whoever runs a site, would only make
     * each verification slower.
     */
    public const int MAX_BITS = 8192;

    /**
     * The object identifier `rsaEncryption`, 1.2.840.113549.1.1.1, with its empty parameters, as DER.
     */
    private const string ALGORITHM = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

    private function __construct(
        private OpenSSLAsymmetricKey $key,
    ) {
    }

    public static function fromPem(string $pem): self
    {
        $key = openssl_pkey_get_public($pem);

        if (false === $key) {
            throw InvalidBadge::because('The public key is not a key in PEM.');
        }

        self::details($key);

        return new self($key);
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
        $bits = '' === $modulus ? 0 : \strlen($modulus) * 8 - (8 - \strlen(decbin(\ord($modulus[0]))));

        if ($bits < self::MIN_BITS || $bits > self::MAX_BITS) {
            throw InvalidBadge::because(\sprintf('The modulus of an RSA JSON Web Key has %d to %d bits; this one has %d.', self::MIN_BITS, self::MAX_BITS, $bits));
        }

        if ('' === $exponent || \strlen($exponent) > 8 || 0 === (\ord($exponent[-1]) & 1) || "\x01" === $exponent) {
            throw InvalidBadge::because('The exponent of an RSA JSON Web Key is odd, at least 3 and 8 bytes at most.');
        }

        $publicKey = self::der("\x30", self::der("\x02", self::unsigned($modulus)).self::der("\x02", self::unsigned($exponent)));
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(sodium_bin2base64(self::der("\x30", self::ALGORITHM.self::der("\x03", "\x00".$publicKey)), \SODIUM_BASE64_VARIANT_ORIGINAL), 64, "\n")."-----END PUBLIC KEY-----\n";

        return self::fromPem($pem);
    }

    /**
     * The details of an RSA key; refused when the key is of another type.
     *
     * @internal
     *
     * @return array<array-key, mixed>
     */
    public static function details(OpenSSLAsymmetricKey $key): array
    {
        $details = openssl_pkey_get_details($key);

        if (false === $details || \OPENSSL_KEYTYPE_RSA !== ($details['type'] ?? null)) {
            throw InvalidBadge::because('The key is not an RSA key.');
        }

        return $details;
    }

    /**
     * @internal
     */
    public static function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw InvalidBadge::because('OpenSSL did not give the parts of the RSA key.');
        }

        return $value;
    }

    private static function decode(string $text): string
    {
        try {
            return Base64Url::decode($text);
        } catch (InvalidCredential) {
            throw InvalidBadge::because('The parts of an RSA JSON Web Key are in URL-safe base 64.');
        }
    }

    /**
     * An unsigned integer as the content of a DER `INTEGER`: a zero byte before a first byte whose high bit is set.
     */
    private static function unsigned(string $bytes): string
    {
        return \ord($bytes[0]) >= 0x80 ? "\x00".$bytes : $bytes;
    }

    /**
     * A DER element: its tag, its length in the short form or on two bytes — a modulus of `MAX_BITS` keeps every element
     * of the key under 65,536 bytes —, and its content.
     */
    private static function der(string $tag, string $content): string
    {
        $length = \strlen($content);

        if ($length < 0x80) {
            return $tag.\chr($length).$content;
        }

        return $tag."\x82".pack('n', $length).$content;
    }

    #[Override]
    public function algorithm(): string
    {
        return 'RS256';
    }

    #[Override]
    public function verify(string $data, string $signature): bool
    {
        return 1 === openssl_verify($data, $signature, $this->key, \OPENSSL_ALGO_SHA256);
    }
}
