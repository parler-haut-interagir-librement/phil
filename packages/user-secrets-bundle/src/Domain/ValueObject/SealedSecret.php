<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\ValueObject;

use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use SodiumException;

/**
 * A secret as it rests in the store: the identifier of the key that sealed it, and the sealed bytes in base64, whose
 * first byte is the version of their format.
 */
final readonly class SealedSecret
{
    public function __construct(
        public string $keyId,
        public string $ciphertext,
    ) {
    }

    /**
     * The format the first byte names, or null when the value is not base64 or names no known format.
     */
    public function format(): ?SealedFormat
    {
        $raw = $this->bytes();

        return null === $raw || '' === $raw ? null : SealedFormat::tryFrom(\ord($raw[0]));
    }

    /**
     * The sealed bytes, or null when the value is not base64.
     */
    public function bytes(): ?string
    {
        try {
            return sodium_base642bin($this->ciphertext, \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            return null;
        }
    }
}
