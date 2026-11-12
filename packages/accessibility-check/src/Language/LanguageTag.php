<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Language;

/**
 * Whether a text is a well-formed language tag of BCP 47 — `fr`, `fr-CA`, `zh-Hant-TW`, `sr-Latn`, `x-private` —, as
 * a screen reader needs it to choose its voice. Well-formed, not registered: the shape of each subtag is checked, not
 * its presence in the registry of IANA. The irregular tags kept for compatibility, such as `i-klingon`, are refused.
 */
final class LanguageTag
{
    private const string WELL_FORMED = '/^(?:(?:[a-z]{2,3}(?:-[a-z]{3}){0,3}|[a-z]{5,8})(?:-[a-z]{4})?(?:-(?:[a-z]{2}|\d{3}))?(?:-(?:[a-z\d]{5,8}|\d[a-z\d]{3}))*(?:-[a-wyz\d](?:-[a-z\d]{2,8})+)*(?:-x(?:-[a-z\d]{1,8})+)?|x(?:-[a-z\d]{1,8})+)$/iD';

    public static function isWellFormed(string $tag): bool
    {
        return 1 === preg_match(self::WELL_FORMED, $tag);
    }

    /**
     * The language of the tag without its region, script or variant, in lower case: `fr` of `fr-CA`.
     */
    public static function primary(string $tag): string
    {
        return strtolower(explode('-', str_replace('_', '-', $tag))[0]);
    }
}
