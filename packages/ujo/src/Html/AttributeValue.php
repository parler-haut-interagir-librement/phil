<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

/**
 * What an attribute of an inline tool may hold: each case cleans a value, or refuses it with `null`.
 */
enum AttributeValue
{
    /** A link: one of the schemes of `SafeUrl::LINK`, or relative. */
    case Link;

    /** A source: `http`, `https` or relative. */
    case Media;

    /** A name: 1 to 64 letters, digits, `_` or `-`. */
    case Token;

    /** A reference to something of a kind, `kind:identifier`: a mention, a wiki link, an icon. */
    case Reference;

    /** Any text, up to 1 000 characters. */
    case Text;

    /** A link's target: `_blank` only. */
    case Target;

    /** A link's relations, among `nofollow`, `noopener`, `noreferrer`, `ugc`, `sponsored` and `external`. */
    case Rel;

    /** A CSS length: `12px`, `1.5em`, `50%`, `-3cm`. */
    case Length;

    /** A whole number of 1 to 5 digits. */
    case Number;

    /** A language tag, well-formed in BCP 47: `en`, `fr-CA`, `zh-Hant-TW`. */
    case Language;

    /** A direction of writing: `ltr`, `rtl` or `auto`. */
    case Direction;

    /** A keyword or a number of MathML: 1 to 32 letters, digits, `.`, `%`, `_` or `-`. */
    case Keyword;

    private const array RELATIONS = ['nofollow', 'noopener', 'noreferrer', 'ugc', 'sponsored', 'external'];

    public function clean(string $value): ?string
    {
        return match ($this) {
            self::Link => SafeUrl::isSafe($value) ? $value : null,
            self::Media => SafeUrl::isSafe($value, SafeUrl::MEDIA) ? $value : null,
            self::Token => self::matches('/^[A-Za-z0-9_-]{1,64}$/D', $value),
            self::Reference => self::matches('~^[a-z][a-z0-9_-]{0,31}:[A-Za-z0-9_./-]{1,128}$~D', $value),
            self::Text => mb_strlen($value) > 1000 ? null : $value,
            self::Target => '_blank' === strtolower($value) ? '_blank' : null,
            self::Rel => self::relations($value),
            self::Length => self::matches('/^-?\d{1,4}(?:\.\d{1,3})?(?:px|em|rem|%|mm|cm|in|pt|ch)$/D', $value),
            self::Number => self::matches('/^\d{1,5}$/D', $value),
            self::Language => LanguageTag::isValid($value) ? $value : null,
            self::Direction => \in_array(strtolower($value), ['ltr', 'rtl', 'auto'], true) ? strtolower($value) : null,
            self::Keyword => self::matches('/^[A-Za-z0-9.%_-]{1,32}$/D', $value),
        };
    }

    private static function matches(string $pattern, string $value): ?string
    {
        return 1 === preg_match($pattern, $value) ? $value : null;
    }

    private static function relations(string $value): ?string
    {
        preg_match_all('/[a-z]+/', strtolower($value), $words);
        $relations = array_intersect(self::RELATIONS, $words[0]);

        return [] === $relations ? null : implode(' ', $relations);
    }
}
