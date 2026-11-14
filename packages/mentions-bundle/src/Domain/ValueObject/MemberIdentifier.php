<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\ValueObject;

use Phil\MentionsBundle\Domain\Exception\InvalidMention;

/**
 * A member as a mention keeps them, and as their notifications reach them: a stable identifier the application gives,
 * `user:42`, never an email address, so an identifier holding `@` is refused.
 */
final readonly class MemberIdentifier
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidMention when the identifier holds "@"
     */
    public static function checked(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            throw InvalidMention::because('A member is named by a stable identifier the application gives, never an email address: it cannot contain "@".');
        }

        return $identifier;
    }
}
