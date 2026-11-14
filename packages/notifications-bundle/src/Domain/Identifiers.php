<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Domain;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;

/**
 * The two shapes a notification is made of. A key names something the application declares — a notification type, a
 * kind of subject, a data entry, an origin —: lowercase ASCII letters, digits, `_`, `.` and `-`, starting with a letter,
 * 64 characters at most. An identifier points at a record — a recipient, an actor, a subject —: ASCII letters, digits,
 * `_`, `.`, `:` and `-`, 190 characters at most. Neither holds a space nor an `@`: a notification carries references,
 * never a name, an address or a sentence.
 */
final class Identifiers
{
    public const int KEY_LENGTH = 64;

    public const int ID_LENGTH = 190;

    private const string KEY = '/^[a-z][a-z0-9_.\-]{0,63}$/D';

    private const string ID = '/^[A-Za-z0-9][A-Za-z0-9_.:\-]{0,189}$/D';

    /**
     * @throws InvalidNotification when the value is not a key
     */
    public static function key(string $value, string $what): string
    {
        if (1 !== preg_match(self::KEY, $value)) {
            throw InvalidNotification::because(\sprintf('The %s "%s" is not a key: lowercase letters, digits, "_", "." and "-", starting with a letter, %d characters at most.', $what, self::excerpt($value), self::KEY_LENGTH));
        }

        return $value;
    }

    /**
     * @throws InvalidNotification when the value is not an identifier
     */
    public static function id(string $value, string $what): string
    {
        if (1 !== preg_match(self::ID, $value)) {
            throw InvalidNotification::because(\sprintf('The %s "%s" is not an identifier: letters, digits, "_", ".", ":" and "-", %d characters at most.', $what, self::excerpt($value), self::ID_LENGTH));
        }

        return $value;
    }

    private static function excerpt(string $value): string
    {
        return mb_strcut($value, 0, 40);
    }
}
