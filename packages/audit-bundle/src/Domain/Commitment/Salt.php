<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Commitment;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The salt of a commitment, specific to one row and one field.
 *
 * **At least 128 bits, drawn from a CSPRNG.** The salt is the HMAC key: it is what makes the commitment
 * irreversible once deleted. A short or predictable salt turns the commitment on an email address into a
 * dictionary attack rather than a redaction.
 *
 * Erasure deletes the plaintext **and the salt**, and keeps the commitment: the row hash does not change, so
 * every anchor still verifies.
 */
final readonly class Salt
{
    public const int MIN_BYTES = 16;

    private function __construct(
        public string $bytes,
    ) {
    }

    public static function generate(): self
    {
        return new self(random_bytes(self::MIN_BYTES));
    }

    /**
     * To read back a stored salt. A salt shorter than the minimum is refused, whatever its source.
     */
    public static function fromBytes(string $bytes): self
    {
        if (\strlen($bytes) < self::MIN_BYTES) {
            throw InvalidValue::because(\sprintf('A salt is at least %d bytes; this one is %d.', self::MIN_BYTES, \strlen($bytes)));
        }

        return new self($bytes);
    }
}
