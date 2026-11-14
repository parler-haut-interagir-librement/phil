<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Commitment;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A row's salted commitment on a personal field: `HMAC-SHA256(key = salt, message = value)`.
 *
 * **The standard construction, not `H(salt ‖ value)`.** Hashed concatenation is home-made and open to length
 * extension; HMAC is the construction nobody has to defend.
 *
 * The audit chain is computed over the commitments and the non-personal fields, never over the plaintext.
 * That is what lets erasure and tamper detection coexist: the value and its salt go, the commitment stays,
 * and the row hash does not move.
 */
final readonly class Commitment
{
    private const string ALGORITHM = 'sha256';

    /**
     * The length of a raw HMAC-SHA256. A stored commitment of any other length is a truncated column or a
     * different construction, and reading it back as a commitment would make `matches()` fail without saying why.
     */
    private const int LENGTH = 32;

    private function __construct(
        public string $bytes,
    ) {
    }

    public static function of(Salt $salt, string $value): self
    {
        return new self(hash_hmac(self::ALGORITHM, $value, $salt->bytes, true));
    }

    /**
     * To read back a stored commitment.
     */
    public static function fromBytes(string $bytes): self
    {
        if (self::LENGTH !== \strlen($bytes)) {
            throw InvalidValue::because(\sprintf('A commitment is %d bytes; this one is %d.', self::LENGTH, \strlen($bytes)));
        }

        return new self($bytes);
    }

    /**
     * Constant-time comparison. A comparison that stops at the first differing byte lets an attacker measure,
     * byte by byte, how close a guessed value is.
     */
    public function matches(Salt $salt, string $value): bool
    {
        return hash_equals($this->bytes, self::of($salt, $value)->bytes);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->bytes, $other->bytes);
    }
}
