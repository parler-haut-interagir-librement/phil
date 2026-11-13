<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\ValueObject;

use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\Resource\ResourceReference;

/**
 * The reference of an author or of an actor, as a thread or a comment keeps it: a stable identifier the application
 * gives, `user:42`, never an email address, so an identifier holding `@` is refused.
 */
final readonly class Member
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidComment when the identifier holds "@"
     */
    public static function reference(ResourceReference $member): string
    {
        if (str_contains($member->id, '@')) {
            throw InvalidComment::because('A member is named by a stable identifier the application gives, never an email address: it cannot contain "@".');
        }

        return $member->toString();
    }
}
