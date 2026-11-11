<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Key;

use OpenSSLAsymmetricKey;
use Override;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;
use SensitiveParameter;

/**
 * An RSA private key of at least 2048 bits, signing with the `RS256` algorithm through OpenSSL — the algorithm every
 * Open Badges 3.0 verifier supports.
 */
final readonly class RsaSigner implements Signer
{
    public const int MIN_BITS = 2048;

    private function __construct(
        private OpenSSLAsymmetricKey $key,
        private string $publicPem,
        private string $modulus,
        private string $exponent,
    ) {
    }

    public static function fromPem(#[SensitiveParameter] string $pem, #[SensitiveParameter] ?string $passphrase = null): self
    {
        $key = openssl_pkey_get_private($pem, $passphrase);

        if (false === $key) {
            throw InvalidBadge::because('The private key is not a key in PEM, or its passphrase is wrong.');
        }

        $details = RsaKey::details($key);
        $bits = $details['bits'] ?? null;

        if (!\is_int($bits) || $bits < self::MIN_BITS) {
            throw InvalidBadge::because(\sprintf('An RSA key has at least %d bits.', self::MIN_BITS));
        }

        $rsa = $details['rsa'] ?? [];

        return new self($key, RsaKey::text($details['key'] ?? null), RsaKey::text(\is_array($rsa) ? $rsa['n'] ?? null : null), RsaKey::text(\is_array($rsa) ? $rsa['e'] ?? null : null));
    }

    #[Override]
    public function algorithm(): string
    {
        return 'RS256';
    }

    #[Override]
    public function sign(string $data): string
    {
        openssl_sign($data, $signature, $this->key, \OPENSSL_ALGO_SHA256);

        return RsaKey::text($signature);
    }

    #[Override]
    public function publicJwk(): array
    {
        return ['kty' => 'RSA', 'n' => Base64Url::encode($this->modulus), 'e' => Base64Url::encode($this->exponent)];
    }

    #[Override]
    public function verificationKey(): VerificationKey
    {
        return RsaKey::fromPem($this->publicPem);
    }
}
