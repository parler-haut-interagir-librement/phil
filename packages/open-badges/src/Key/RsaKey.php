<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use OpenSSLAsymmetricKey;
use Override;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * An RSA public key, verifying `RS256` signatures through OpenSSL.
 */
final readonly class RsaKey implements VerificationKey
{
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
