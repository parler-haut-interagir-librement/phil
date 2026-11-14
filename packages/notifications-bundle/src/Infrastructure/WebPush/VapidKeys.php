<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\WebPush;

use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;
use SodiumException;

/**
 * The application's VAPID keys (RFC 8292), which tell push services the messages come from it: a contact — a
 * `mailto:` address or an `https:` page — and a P-256 key pair in URL-safe Base64, the public key uncompressed (65
 * bytes), the private key its 32-byte scalar. The public key is what browsers subscribe with; the private one stays in
 * the application's secrets. `phil:notifications:vapid-keys` writes a new pair.
 */
final readonly class VapidKeys
{
    public const int PUBLIC_KEY_BYTES = 65;

    public const int PRIVATE_KEY_BYTES = 32;

    /**
     * @throws InvalidArgumentException when the contact is no `mailto:` or `https:` address, or a key has the wrong length
     */
    public function __construct(
        public string $subject,
        public string $publicKey,
        #[SensitiveParameter]
        public string $privateKey,
    ) {
        if (1 !== preg_match('#^(mailto:[^\s@]+@[^\s@]+|https://[^\s/]+(/\S*)?)$#Di', $subject)) {
            throw new InvalidArgumentException('The VAPID contact is a "mailto:" address or an "https://" page.');
        }

        $public = $this->decoded($publicKey, 'public');

        if (self::PUBLIC_KEY_BYTES !== \strlen($public) || "\x04" !== $public[0]) {
            throw new InvalidArgumentException(\sprintf('The VAPID public key is an uncompressed P-256 point of %d bytes, in URL-safe Base64.', self::PUBLIC_KEY_BYTES));
        }

        if (self::PRIVATE_KEY_BYTES !== \strlen($this->decoded($privateKey, 'private'))) {
            throw new InvalidArgumentException(\sprintf('The VAPID private key is %d bytes, in URL-safe Base64.', self::PRIVATE_KEY_BYTES));
        }
    }

    /**
     * A new key pair on the P-256 curve, made by OpenSSL.
     *
     * @return array{publicKey: string, privateKey: string} in URL-safe Base64, without padding
     *
     * @throws RuntimeException when OpenSSL cannot make an elliptic-curve key
     */
    public static function generate(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => \OPENSSL_KEYTYPE_EC]);
        $details = false === $key ? false : openssl_pkey_get_details($key);

        return self::fromEc(false === $details ? null : ($details['ec'] ?? null));
    }

    /**
     * The key pair of the elliptic-curve details OpenSSL gives of a key: the coordinates `x` and `y` of the public point,
     * and the scalar `d`.
     *
     * @return array{publicKey: string, privateKey: string} in URL-safe Base64, without padding
     *
     * @throws RuntimeException when the details are no P-256 key's
     *
     * @internal
     */
    public static function fromEc(mixed $ec): array
    {
        if (!\is_array($ec) || !\is_string($ec['x'] ?? null) || !\is_string($ec['y'] ?? null) || !\is_string($ec['d'] ?? null)) {
            throw new RuntimeException('OpenSSL cannot make a P-256 key on this system.');
        }

        // OpenSSL drops the leading zero bytes of a coordinate or of the scalar: each is 32 bytes, padded on the left.
        $coordinate = static fn (string $bytes): string => str_pad($bytes, 32, "\0", \STR_PAD_LEFT);

        return [
            'publicKey' => sodium_bin2base64("\x04".$coordinate($ec['x']).$coordinate($ec['y']), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            'privateKey' => sodium_bin2base64($coordinate($ec['d']), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
        ];
    }

    private function decoded(#[SensitiveParameter] string $key, string $which): string
    {
        try {
            return sodium_base642bin(rtrim($key, '='), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            throw new InvalidArgumentException(\sprintf('The VAPID %s key is written in URL-safe Base64.', $which));
        }
    }
}
