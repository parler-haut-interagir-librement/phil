<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\ValueObject;

use Phil\ModerationBundle\Domain\Exception\InvalidModeration;

/**
 * A member, by the stable identifier the application gives it: a UUID, an OIDC `sub`, a member number.
 *
 * **Never an e-mail address**: an address changes, and names the person to whoever reads a report or a decision, so an
 * identifier that looks like one is refused.
 */
final readonly class MemberId
{
    public const int MAX_LENGTH = 190;

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $value)) {
            throw InvalidModeration::because(\sprintf('A member is designated by 1 to %d visible characters.', self::MAX_LENGTH));
        }

        if (1 === preg_match('/^[^@]+@[^@]+\.[^@]+$/D', $value)) {
            throw InvalidModeration::because('A member is designated by a stable identifier, never an e-mail address.');
        }

        return new self($value);
    }

    public static function orNull(?string $value): ?self
    {
        return null === $value ? null : self::of($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
