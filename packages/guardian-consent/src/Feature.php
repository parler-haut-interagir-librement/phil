<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Override;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Stringable;

/**
 * A feature of the site a guardian consents to on its own — the messages, the public profile, the classified ads —,
 * named by the site. The list is open: the library knows none of them.
 *
 * A name is 1 to 64 characters: lowercase letters, digits, `.`, `_` and `-`, beginning with a letter or a digit.
 */
final readonly class Feature implements Stringable
{
    public const int MAX_LENGTH = 64;

    /**
     * @throws InvalidConsent when the name does not follow the rule
     */
    public function __construct(
        public string $name,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $name)) {
            throw InvalidConsent::because('A feature is named by 1 to 64 lowercase letters, digits, ".", "_" or "-", beginning with a letter or a digit.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
