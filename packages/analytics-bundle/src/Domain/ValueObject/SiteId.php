<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Stringable;

/**
 * The reference of a measured site: the unit every counter belongs to.
 *
 * **Opaque.** The application chooses the form (a slug, a UUID); the bundle only refuses what cannot be a stable
 * key: an empty string, surrounding whitespace, control characters, or more than {@see self::MAX_LENGTH} bytes.
 * A site is never a person: nothing in this reference may identify a visitor.
 */
final readonly class SiteId implements Stringable
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidValue::because('A site reference cannot be empty.');
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A site reference is at most %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (trim($value) !== $value) {
            throw InvalidValue::because(\sprintf('The site reference "%s" has surrounding whitespace.', $value));
        }

        if (1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidValue::because('A site reference cannot contain control characters.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
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
