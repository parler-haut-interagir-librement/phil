<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * Locales and the texts kept by locale: a locale such as `fr` or `fr_CA` is checked where it enters, and a text is
 * found for a locale, else for its language, else for a fallback.
 */
final class Locales
{
    public const string PATTERN = '/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8}){0,3}$/D';

    public const int MAX_LOCALES = 32;

    private function __construct()
    {
    }

    /**
     * A locale, its subtags joined by `_`.
     *
     * @throws InvalidLegalNotice
     */
    public static function check(string $locale): string
    {
        $normal = str_replace('-', '_', $locale);

        if (1 !== preg_match(self::PATTERN, $normal)) {
            throw InvalidLegalNotice::because('locale', \sprintf('%s is not a locale such as "en" or "fr_CA".', InvalidLegalNotice::quote($locale)));
        }

        return $normal;
    }

    /**
     * The language of a locale: `fr` for `fr_CA`.
     */
    public static function language(string $locale): string
    {
        return explode('_', $locale)[0];
    }

    /**
     * The value of a locale, else of its language, else `null`.
     *
     * @template T
     *
     * @param array<string, T> $values
     *
     * @return T|null
     */
    public static function find(array $values, string $locale): mixed
    {
        return $values[$locale] ?? $values[self::language($locale)] ?? null;
    }

    /**
     * The value of a locale, else of the fallback locale, else the first value.
     *
     * @template T
     *
     * @param non-empty-array<string, T> $values
     *
     * @return T
     */
    public static function pick(array $values, string $locale, string $fallback): mixed
    {
        return self::find($values, $locale) ?? self::find($values, $fallback) ?? array_first($values);
    }

    /**
     * Texts by locale, each trimmed, not empty, valid UTF-8 and within a size; 32 locales at most.
     *
     * @param array<array-key, mixed> $texts
     *
     * @return array<string, non-empty-string>
     *
     * @throws InvalidLegalNotice
     */
    public static function texts(array $texts, int $maxBytes, string $what): array
    {
        if (\count($texts) > self::MAX_LOCALES) {
            throw InvalidLegalNotice::because('too_many_locales', \sprintf('A %s is given in 32 locales at most.', $what));
        }

        $kept = [];

        foreach ($texts as $locale => $text) {
            $locale = self::check((string) $locale);

            $text = \is_string($text) ? trim($text) : '';

            if ('' === $text) {
                throw InvalidLegalNotice::because('empty_text', \sprintf('The %s in "%s" is empty.', $what, $locale));
            }

            if (\strlen($text) > $maxBytes || !mb_check_encoding($text, 'UTF-8')) {
                throw InvalidLegalNotice::because('invalid_text', \sprintf('The %s in "%s" is UTF-8 text of %d bytes at most.', $what, $locale, $maxBytes));
            }

            $kept[$locale] = $text;
        }

        return $kept;
    }
}
