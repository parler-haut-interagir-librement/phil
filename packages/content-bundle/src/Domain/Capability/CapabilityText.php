<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\Exception\InvalidContent;

/**
 * The checks every free-text value of a capability passes before a row holds it.
 *
 * **A value is stored as it was given, or refused; it is never corrected.** Trimming, or turning an empty
 * string into `null`, is the data mapper's work, on submitted data: a row that silently rewrote what it was
 * handed would store something its caller never saw. So a value must already be trimmed and not empty — an
 * absent value is `null` — and valid UTF-8, without control characters.
 */
final class CapabilityText
{
    /**
     * The length of the `VARCHAR` column a line is stored in, counted in characters.
     */
    public const int LINE_LENGTH = 255;

    /**
     * Multi-line text: line breaks and tabs are allowed, other control characters are not.
     *
     * @throws InvalidContent
     */
    public static function text(string $value, string $field): string
    {
        if (1 !== preg_match('//u', $value)) {
            throw InvalidContent::because(\sprintf('%s is not valid UTF-8.', $field));
        }

        if ('' === $value || trim($value) !== $value) {
            throw InvalidContent::because(\sprintf('%s must be trimmed and not empty; an absent value is null.', $field));
        }

        if (1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw InvalidContent::because(\sprintf('%s contains a control character.', $field));
        }

        return $value;
    }

    /**
     * One line of at most `LINE_LENGTH` characters: no line break and no tab.
     *
     * @throws InvalidContent
     */
    public static function line(string $value, string $field): string
    {
        self::text($value, $field);

        if (1 === preg_match('/[\t\n\r]/', $value)) {
            throw InvalidContent::because(\sprintf('%s is a single line.', $field));
        }

        if (1 !== preg_match('/\A.{0,255}\z/u', $value)) {
            throw InvalidContent::because(\sprintf('%s is longer than %d characters.', $field, self::LINE_LENGTH));
        }

        return $value;
    }

    /**
     * A reference to a resource outside the row — an image's URL or path, a media library's identifier: at
     * most 2048 printable ASCII characters, without spaces. What it points to is the application's.
     *
     * @throws InvalidContent
     */
    public static function reference(string $value, string $field): string
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,2048}$/D', $value)) {
            throw InvalidContent::because(\sprintf('%s must be 1 to 2048 printable ASCII characters without spaces; percent-encode anything else.', $field));
        }

        return $value;
    }
}
