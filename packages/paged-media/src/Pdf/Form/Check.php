<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Pdf\PageSize;

/**
 * What every form field checks when it is built.
 *
 * @internal
 */
final class Check
{
    /** The longest field name or export value, in bytes: the most a PDF name or a reader's field list holds well. */
    public const int MAXIMUM_NAME = 127;

    /**
     * A field's name: not empty, no period, which would make it a child of another field, at most 127 bytes.
     */
    public static function name(string $name): string
    {
        if ('' === $name || str_contains($name, '.') || \strlen($name) > self::MAXIMUM_NAME || 1 !== preg_match('//u', $name)) {
            throw InvalidDocument::because(\sprintf('A field name is UTF-8 text of 1 to %d bytes without a period; "%s" is not.', self::MAXIMUM_NAME, $name));
        }

        return $name;
    }

    /**
     * The value a button sets: a name, never `Off`, which means unchecked.
     */
    public static function exportValue(string $value): string
    {
        if ('' === $value || 'Off' === $value || \strlen($value) > self::MAXIMUM_NAME || 1 !== preg_match('//u', $value)) {
            throw InvalidDocument::because(\sprintf('An export value is UTF-8 text of 1 to %d bytes other than "Off"; "%s" is not.', self::MAXIMUM_NAME, $value));
        }

        return $value;
    }

    /**
     * A measure of a field: above zero and no larger than the largest page.
     */
    public static function measure(float $value, string $what): float
    {
        if ($value <= 0 || $value > PageSize::MAXIMUM) {
            throw InvalidDocument::because(\sprintf('A field %s is above zero and at most %d points; %s is not.', $what, PageSize::MAXIMUM, $value));
        }

        return $value;
    }

    /**
     * A text a field shows: UTF-8, and on one line unless the field is multiline.
     */
    public static function text(string $text, bool $multiline, string $what): string
    {
        if (1 !== preg_match('//u', $text) || (!$multiline && false !== strpbrk($text, "\r\n"))) {
            throw InvalidDocument::because(\sprintf('A %s is UTF-8 text%s.', $what, $multiline ? '' : ' on one line'));
        }

        return $text;
    }
}
