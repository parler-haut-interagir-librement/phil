<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * The shape every identifier of the bundle keeps: letters, digits and `.`, `_`, `:`, `-`, starting with a letter or a
 * digit, and bounded in length, so that it is safe in a URL, a log line and a column.
 */
final class Identifiers
{
    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\-]*$/D';

    private function __construct()
    {
    }

    /**
     * @param positive-int $maxLength
     *
     * @throws InvalidCourse when the value is empty, too long or holds another character
     */
    public static function check(string $what, string $value, int $maxLength): string
    {
        if ('' === $value || \strlen($value) > $maxLength || 1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidCourse::because(\sprintf('%s identifier is 1 to %d letters, digits, ".", "_", ":" or "-", starting with a letter or a digit.', $what, $maxLength));
        }

        return $value;
    }

    /**
     * A title as the pages show it: trimmed, one to 255 characters.
     *
     * @throws InvalidCourse when it is empty or longer
     */
    public static function title(string $title): string
    {
        $title = trim($title);

        if ('' === $title || mb_strlen($title) > 255) {
            throw InvalidCourse::because('A title is 1 to 255 characters.');
        }

        return $title;
    }
}
