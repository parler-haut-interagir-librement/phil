<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Signature;

use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;

/**
 * The public half of an Ed25519 key: what the console keeps of each site, and what each site keeps of the console.
 */
final readonly class VerifyingKey
{
    /**
     * @param non-empty-string $bytes
     */
    private function __construct(
        private string $bytes,
    ) {
    }

    /**
     * @throws InvalidValue when the text is not the base64 of 32 bytes
     */
    public static function fromBase64(string $text): self
    {
        $bytes = Base64Url::decodeKey($text);

        if (null === $bytes || \SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== \strlen($bytes)) {
            throw InvalidValue::because('A public key is the base64 of the 32 bytes of an Ed25519 key.');
        }

        return new self($bytes);
    }

    public static function fromBytes(string $bytes): self
    {
        if ('' === $bytes || \SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== \strlen($bytes)) {
            throw InvalidValue::because('A public key is the 32 bytes of an Ed25519 key.');
        }

        return new self($bytes);
    }

    /**
     * Whether the signature, in base64url, is this key's signature of the message.
     */
    public function verifies(string $message, string $signature): bool
    {
        $bytes = Base64Url::decode($signature);

        return null !== $bytes && \SODIUM_CRYPTO_SIGN_BYTES === \strlen($bytes) && sodium_crypto_sign_verify_detached($bytes, $message, $this->bytes);
    }

    /**
     * Whether both are the same key, compared in constant time.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->bytes, $other->bytes);
    }

    public function toBase64(): string
    {
        return Base64Url::encode($this->bytes);
    }
}
