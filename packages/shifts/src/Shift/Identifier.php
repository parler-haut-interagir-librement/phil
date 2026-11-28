<?php

declare(strict_types=1);

namespace Phil\Shifts\Shift;

use Phil\Shifts\Exception\InvalidShift;

/**
 * The rules of the identifiers and texts:
 *
 * - a shift or a swap offer: 1 to 64 lowercase letters, digits, `_`, `.` and `-`, starting with a letter or a digit —
 *   a UUID fits;
 * - a volunteer: 1 to 191 characters the application gives, without control characters or surrounding spaces;
 * - a role: 1 to 64 letters, digits, `_`, `.`, `:` and `-`;
 * - a text: within its length in characters, without control characters other than line breaks and tabs.
 */
final class Identifier
{
    public const int VOLUNTEER_LENGTH = 191;

    private function __construct()
    {
    }

    /**
     * @throws InvalidShift when the identifier is not one
     */
    public static function key(string $value, string $field): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $value)) {
            throw InvalidShift::because(\sprintf('The %s is 1 to 64 lowercase letters, digits, "_", "." and "-", starting with a letter or a digit.', $field));
        }

        return $value;
    }

    /**
     * @throws InvalidShift when the identifier is not one
     */
    public static function volunteer(string $value): string
    {
        if ('' === $value || mb_strlen($value) > self::VOLUNTEER_LENGTH || trim($value) !== $value || 1 === preg_match('/\p{Cc}/u', $value) || !mb_check_encoding($value, 'UTF-8')) {
            throw InvalidShift::because('The volunteer is 1 to 191 characters, without control characters or surrounding spaces.');
        }

        return $value;
    }

    /**
     * @throws InvalidShift when the role is not one
     */
    public static function role(string $value): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $value)) {
            throw InvalidShift::because('A role is 1 to 64 letters, digits, "_", ".", ":" and "-".');
        }

        return $value;
    }

    /**
     * @throws InvalidShift when the text is empty when it must not be, too long, or holds control characters
     */
    public static function text(string $value, string $field, int $length, bool $required = true): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $length || ($required && '' === trim($value)) || 1 === preg_match('/[^\P{Cc}\t\n\r]/u', $value)) {
            throw InvalidShift::because(\sprintf('The %s is %s %d characters at most, without control characters.', $field, $required ? 'a text of' : 'empty or a text of', $length));
        }

        return $value;
    }
}
