<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Encryption;

/**
 * The version of a sealed value, its first byte, which tells a reader how to open it. A value whose first byte names
 * no case is refused. Values are sealed in the current format; values of an earlier one still open, and the key
 * rotation seals them again in the current one.
 */
enum SealedFormat: int
{
    /**
     * Envelope encryption through Symfony's KeyManagement component, the algorithm implied: the byte, then a
     * self-contained envelope — the data key wrapped by a master key of the KMS, the nonce, the tag and the ciphertext,
     * AES-256-GCM. Read, never written.
     */
    case KeyManagement = 2;

    /**
     * The byte, the byte of the {@see SealedAlgorithm} that sealed the value, then what that algorithm wrote. Both
     * bytes are authenticated with the value: a header changed to another version or algorithm does not open.
     */
    case Algorithm = 3;

    /**
     * The format every new value is sealed in.
     */
    public static function current(): self
    {
        return self::Algorithm;
    }
}
