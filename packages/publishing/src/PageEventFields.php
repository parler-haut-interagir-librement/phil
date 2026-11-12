<?php

declare(strict_types=1);

namespace Phil\Publishing;

use Phil\Publishing\Exception\InvalidPageEvent;

/**
 * The checks both events make of their language and owner identifier.
 *
 * @internal
 */
final class PageEventFields
{
    public const int MAX_LOCALE_LENGTH = 35;

    public const int MAX_OWNER_ID_LENGTH = 255;

    private function __construct()
    {
    }

    /**
     * A BCP 47 tag as an application writes one: a primary language of two or three letters, then subtags of one to
     * eight letters or digits, separated by hyphens; 35 characters at most. An underscore, as in `fr_CA`, is refused:
     * that is a locale identifier of ICU, not a language tag.
     *
     * @throws InvalidPageEvent
     */
    public static function locale(string $locale): string
    {
        if (\strlen($locale) > self::MAX_LOCALE_LENGTH || 1 !== preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8})*$/D', $locale)) {
            throw InvalidPageEvent::locale($locale);
        }

        return $locale;
    }

    /**
     * @throws InvalidPageEvent
     */
    public static function ownerId(string $ownerId): string
    {
        if ('' === $ownerId || \strlen($ownerId) > self::MAX_OWNER_ID_LENGTH || trim($ownerId) !== $ownerId || 1 === preg_match('/[\x00-\x1F\x7F]/', $ownerId)) {
            throw InvalidPageEvent::ownerId($ownerId, self::MAX_OWNER_ID_LENGTH);
        }

        return $ownerId;
    }
}
