<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use Phil\FeedBundle\Domain\Exception\InvalidFeed;

/**
 * The checks the parts of a feed make of their texts: UTF-8, bounded, and, for a single line, without a control
 * character.
 *
 * @internal
 */
final readonly class Text
{
    private const string LANGUAGE = '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8})*$/D';

    private const int MAX_LANGUAGE_LENGTH = 35;

    private const string IRI = '/^[A-Za-z][A-Za-z0-9+.-]*:[^\s\x00-\x1F\x7F]+$/D';

    private const string CONTROL = '/[\x00-\x1F\x7F]/';

    private function __construct()
    {
    }

    /**
     * A single line: UTF-8, 1 to `$max` characters once trimmed, without a control character.
     *
     * @throws InvalidFeed
     */
    public static function line(string $field, string $value, int $max): string
    {
        self::utf8($field, $value);
        $trimmed = trim($value);

        if ('' === $trimmed || mb_strlen($trimmed) > $max || 1 === preg_match(self::CONTROL, $trimmed)) {
            throw InvalidFeed::because($field, \sprintf('1 to %d characters on one line', $max), $value);
        }

        return $trimmed;
    }

    /**
     * A text of several lines, `null` when empty once trimmed: UTF-8, at most `$max` bytes.
     *
     * @throws InvalidFeed
     */
    public static function block(string $field, ?string $value, int $max): ?string
    {
        if (null === $value) {
            return null;
        }

        self::utf8($field, $value);
        $trimmed = trim($value);

        if (\strlen($trimmed) > $max) {
            throw InvalidFeed::because($field, \sprintf('at most %d bytes', $max), $value);
        }

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * A BCP 47 tag as an application writes one: `fr`, `fr-CA`, `zh-Hant-TW`; 35 characters at most.
     *
     * @throws InvalidFeed
     */
    public static function language(?string $value): ?string
    {
        if (null !== $value && (\strlen($value) > self::MAX_LANGUAGE_LENGTH || 1 !== preg_match(self::LANGUAGE, $value))) {
            throw InvalidFeed::because('language', 'a BCP 47 tag such as fr or fr-CA', $value);
        }

        return $value;
    }

    /**
     * An absolute IRI, as Atom asks of an identifier: a scheme, a colon, then no space nor control character.
     *
     * @throws InvalidFeed
     */
    public static function iri(string $field, string $value, int $max): string
    {
        self::utf8($field, $value);

        if (\strlen($value) > $max || 1 !== preg_match(self::IRI, $value)) {
            throw InvalidFeed::because($field, \sprintf('an absolute IRI of at most %d bytes, such as tag:example.org,2026:news-42 or https://example.org/news/42', $max), $value);
        }

        return $value;
    }

    /**
     * @throws InvalidFeed
     */
    private static function utf8(string $field, string $value): void
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidFeed::because($field, 'not UTF-8', $value);
        }
    }
}
