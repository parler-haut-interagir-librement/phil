<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * The identifier of a team: a UUID version 7 when the bundle creates one, or any identifier of the
 * application that follows the rule of `Identifier`.
 */
final readonly class TeamId
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        return new self(Identifier::check($value, 'team identifier'));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
