<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain;

use Phil\TermsBundle\Domain\Exception\InvalidTerms;

/**
 * The shapes of the identifiers the terms keep: a policy key, a member identifier, a locale and a licence. Each is checked where
 * it enters, so that a stored row, a URL and a notification only ever carry what these allow.
 */
final class Identifiers
{
    public const int MAX_MEMBER_LENGTH = 190;

    public const int MAX_LOCALE_LENGTH = 35;

    public const int MAX_LICENCE_LENGTH = 64;

    private function __construct()
    {
    }

    /**
     * A policy key: a lowercase letter, then lowercase letters, digits, `_` and `-`, 64 characters at most.
     *
     * @throws InvalidTerms
     */
    public static function policy(string $key): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key)) {
            throw InvalidTerms::because('policy_key', 'A policy key is a lowercase letter, then lowercase letters, digits, "_" and "-", 64 characters at most: "%key%" is not.', ['%key%' => mb_substr($key, 0, 80)]);
        }

        return $key;
    }

    /**
     * A member identifier: 190 bytes at most, without white space or control characters — a stable identifier, never
     * a name or a sentence.
     *
     * @throws InvalidTerms
     */
    public static function member(string $member): string
    {
        if ('' === $member || \strlen($member) > self::MAX_MEMBER_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $member)) {
            throw InvalidTerms::because('member', 'A member identifier holds 1 to 190 bytes, without white space or control characters.');
        }

        return $member;
    }

    /**
     * A locale such as `fr` or `fr_CA`: a language of two or three lowercase letters, then subtags.
     *
     * @throws InvalidTerms
     */
    public static function locale(string $locale): string
    {
        if (\strlen($locale) > self::MAX_LOCALE_LENGTH || 1 !== preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/D', $locale)) {
            throw InvalidTerms::because('locale', '"%locale%" is not a locale.', ['%locale%' => mb_substr($locale, 0, 40)]);
        }

        return $locale;
    }

    /**
     * A licence, by its SPDX identifier such as `CC-BY-SA-4.0` or `LicenseRef-site`: a letter or a digit, then letters,
     * digits, `.`, `-` and `+`, 64 characters at most — one identifier, never an expression or a sentence.
     *
     * @throws InvalidTerms
     */
    public static function licence(string $licence): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9.+-]{0,63}$/D', $licence)) {
            throw InvalidTerms::because('licence', 'A licence is an SPDX identifier such as "CC-BY-SA-4.0": "%licence%" is not.', ['%licence%' => mb_substr($licence, 0, 80)]);
        }

        return $licence;
    }
}
