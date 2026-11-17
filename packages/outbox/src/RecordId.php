<?php

declare(strict_types=1);

namespace Phil\Outbox;

use Override;
use Phil\Outbox\Exception\InvalidRecord;
use Stringable;

/**
 * The stable identifier of a record: 32 lowercase hexadecimal digits, 128 random bits. A listener reads it to tell a
 * record delivered again from a new one, since the relay delivers each record at least once.
 */
final readonly class RecordId implements Stringable
{
    /** The shape of an identifier. */
    public const string PATTERN = '/^[0-9a-f]{32}$/D';

    /**
     * @param non-empty-string $value
     */
    private function __construct(
        public string $value,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * A new identifier, from the random source of the system.
     */
    public static function generate(): self
    {
        return new self(sodium_bin2hex(random_bytes(16)));
    }

    /**
     * @throws InvalidRecord when the value is not 32 lowercase hexadecimal digits
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidRecord::because(\sprintf('"%s" is not a record identifier: 32 lowercase hexadecimal digits.', mb_strimwidth($value, 0, 40, '…')));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
