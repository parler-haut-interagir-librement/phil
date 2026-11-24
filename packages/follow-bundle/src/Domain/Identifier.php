<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain;

use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The shapes of the bundle, those of the notifications it sends: a member, an actor or an activity is an identifier —
 * letters, digits, `_`, `.`, `:` and `-`, 190 characters at most —, an event a key — lowercase, starting with a letter,
 * 64 characters at most —, so that no name, address or sentence is ever kept or carried.
 */
final class Identifier
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidFollow when the value is not an identifier
     */
    public static function id(string $value, string $what): string
    {
        try {
            return Identifiers::id($value, $what);
        } catch (InvalidNotification $invalidNotification) {
            throw InvalidFollow::because($invalidNotification->getMessage());
        }
    }

    /**
     * @throws InvalidFollow when the value is not a key
     */
    public static function key(string $value, string $what): string
    {
        try {
            return Identifiers::key($value, $what);
        } catch (InvalidNotification $invalidNotification) {
            throw InvalidFollow::because($invalidNotification->getMessage());
        }
    }

    /**
     * @throws InvalidFollow when the kind is not a key or the identifier is not one
     */
    public static function subject(string $type, string $id): SubjectReference
    {
        try {
            return new SubjectReference($type, $id);
        } catch (InvalidNotification $invalidNotification) {
            throw InvalidFollow::because($invalidNotification->getMessage());
        }
    }

    /**
     * Reads the `type:id` that `SubjectReference::key()` writes; the identifier may hold colons of its own.
     *
     * @throws InvalidFollow when the reference is not of that form
     */
    public static function parseSubject(string $reference): SubjectReference
    {
        $parts = explode(':', $reference, 2);

        if (2 !== \count($parts)) {
            throw InvalidFollow::because('A subject is written "type:identifier".');
        }

        return self::subject($parts[0], $parts[1]);
    }
}
