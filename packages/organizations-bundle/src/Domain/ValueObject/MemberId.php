<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

/**
 * A member, by the opaque subject identifier of the application's user provider — a ULID, a UUID, the `sub` of an
 * OpenID Connect provider —, never an email address. The bundle knows nothing else of the member, so it works with any
 * user provider.
 */
final readonly class MemberId
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(Identifier::check($value, 'member identifier'));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
