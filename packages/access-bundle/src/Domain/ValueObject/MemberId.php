<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Domain\ValueObject;

use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\Resource\ResourceReference;

/**
 * A member by a stable identifier that carries no personal data — a ULID, a UUID, a number —, never an email address:
 * an identifier with `@` is refused, so that a site whose user identifier is the email address finds out at once
 * rather than writing addresses into tuples, logs and events.
 */
final readonly class MemberId
{
    public const string KIND = 'member';

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        if (str_contains($value, '@')) {
            throw InvalidAccess::because('A member is named by a stable identifier, never an email address: give the bundle a CurrentMember that returns one.');
        }

        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D', $value)) {
            throw InvalidAccess::because(\sprintf('A member identifier is 1 to 128 letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $value));
        }

        return new self($value);
    }

    public function reference(): ResourceReference
    {
        return ResourceReference::of(self::KIND, $this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
