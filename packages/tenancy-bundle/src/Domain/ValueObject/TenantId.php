<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Stringable;

/**
 * The reference of a tenant inside one application.
 *
 * **Scoped to the application, never global.** A tenant is keyed on `(application, reference)`: the same
 * reference in two applications names two unrelated tenants. This type therefore carries the reference and
 * nothing else, and it is only ever compared with references of the same application.
 *
 * **Opaque.** The application chooses the form (a slug, a UUID, a number); the bundle only refuses what cannot
 * be a stable key: an empty string, invalid UTF-8, surrounding whitespace (Unicode spaces included, such as a
 * no-break space), control characters (C0, DEL and C1), or more than {@see self::MAX_LENGTH} bytes. Two
 * references that display alike must not be two tenants.
 */
final readonly class TenantId implements Stringable
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidValue::because('A tenant reference cannot be empty.');
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A tenant reference is at most %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 !== preg_match('//u', $value)) {
            throw InvalidValue::because('A tenant reference is valid UTF-8.');
        }

        if (1 === preg_match('/^[\s\p{Z}]|[\s\p{Z}]$/u', $value)) {
            throw InvalidValue::because(\sprintf('The tenant reference "%s" has surrounding whitespace.', $value));
        }

        if (1 === preg_match('/\p{Cc}/u', $value)) {
            throw InvalidValue::because('A tenant reference cannot contain control characters.');
        }

        return new self($value);
    }

    /**
     * Byte-for-byte comparison. Two references that differ only by case are two tenants: folding case here
     * would let one tenant's member act in another whose reference happens to collide.
     */
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
