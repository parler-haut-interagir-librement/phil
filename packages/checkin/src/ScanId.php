<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Phil\Checkin\Exception\InvalidCheckin;

/**
 * The identifier of one scan, drawn by the device that makes it: a scan sent twice — a retry after a lost answer, an
 * offline journal synchronised again — is recorded once, and the second sending is answered with the first decision.
 *
 * Letters, digits, `.`, `_`, `:` and `-`, at most 64 bytes, such as a UUID.
 */
final readonly class ScanId
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidCheckin
     */
    public static function of(string $value): self
    {
        return new self(Identifier::check($value, 'scan identifier'));
    }

    /**
     * A new identifier of 128 random bits, as 32 hexadecimal digits.
     */
    public static function generate(): self
    {
        return new self(sodium_bin2hex(random_bytes(16)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
