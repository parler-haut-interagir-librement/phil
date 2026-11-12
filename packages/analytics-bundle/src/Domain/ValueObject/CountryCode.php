<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Stringable;

/**
 * The country of a page view, an ISO 3166-1 alpha-2 code, or unknown.
 *
 * The country is derived from the client address in memory, then the address is forgotten: the country is the
 * only thing about the address a counter ever holds. When the lookup cannot answer, or its database is too old
 * to be used, the country is {@see self::unknown()}, counted under {@see CounterKey::UNKNOWN}.
 */
final readonly class CountryCode implements Stringable
{
    private function __construct(
        public ?string $code,
    ) {
    }

    public static function fromString(string $code): self
    {
        if (1 !== preg_match('/^[A-Za-z]{2}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not an ISO 3166-1 alpha-2 country code.', $code));
        }

        return new self(strtoupper($code));
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    public function isKnown(): bool
    {
        return null !== $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * The dimension value the country is counted under.
     */
    public function toString(): string
    {
        return $this->code ?? CounterKey::UNKNOWN;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
