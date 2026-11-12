<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Phil\IpAnonymizer\Exception\InvalidSalt;
use SensitiveParameter;

/**
 * The secret key of a keyed digest, with the identifier of its period.
 *
 * **Secret, and at least 16 bytes; 32 are recommended.** An unkeyed hash of an IPv4 address is reversed by hashing
 * its 2^32 values; the salt is what keeps the digest irreversible to whoever reads it without holding the salt.
 *
 * **Identified.** The identifier enters the digest's text, so two salts never give comparable values. It is 1 to 32
 * characters among letters, digits, `.`, `-` and `_`.
 */
final readonly class Salt
{
    public const int MIN_BYTES = \SODIUM_CRYPTO_GENERICHASH_KEYBYTES_MIN;

    public const int MAX_BYTES = \SODIUM_CRYPTO_GENERICHASH_KEYBYTES_MAX;

    public const int RECOMMENDED_BYTES = \SODIUM_CRYPTO_GENERICHASH_KEYBYTES;

    private function __construct(
        public string $id,
        public string $bytes,
    ) {
    }

    /**
     * @throws InvalidSalt when the identifier or the length of the bytes is out of bounds
     */
    public static function of(string $id, #[SensitiveParameter] string $bytes): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9._-]{1,32}$/D', $id)) {
            throw InvalidSalt::because('A salt identifier is 1 to 32 characters among letters, digits, ".", "-" and "_".');
        }

        $length = \strlen($bytes);

        if ($length < self::MIN_BYTES || $length > self::MAX_BYTES) {
            throw InvalidSalt::because(\sprintf('A salt is %d to %d bytes long; this one is %d.', self::MIN_BYTES, self::MAX_BYTES, $length));
        }

        return new self($id, $bytes);
    }

    /**
     * A salt of 32 random bytes from the system's secure source.
     */
    public static function random(string $id): self
    {
        return self::of($id, random_bytes(self::RECOMMENDED_BYTES));
    }

    /**
     * @return array{id: string, bytes: string}
     */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'bytes' => '[redacted]'];
    }
}
