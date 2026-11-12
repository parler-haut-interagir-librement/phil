<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Stringable;

/**
 * What a rate limiter keeps of a client address: the text `phil/ip-anonymizer` makes, `<salt id>:<hex digest>`.
 *
 * Its own type, checked on entry, so that a bare address can never be handed to a limiter in its place.
 */
final readonly class ClientDigest implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9._-]{1,32}:[0-9a-f]{32,128}$/D', $value)) {
            throw InvalidValue::because('A client digest is written "<salt id>:<hexadecimal digest>".');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
