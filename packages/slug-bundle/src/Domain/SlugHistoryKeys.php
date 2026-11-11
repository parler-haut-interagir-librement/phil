<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Domain;

use Phil\SlugBundle\Domain\Exception\InvalidSlugHistory;

/**
 * What the slug history accepts as a scope and as a subject, checked before any query so that a value too long for
 * its column, or one that would read differently once stored, is refused rather than cut.
 */
final class SlugHistoryKeys
{
    /** The longest scope, in bytes. */
    public const int SCOPE_MAX_LENGTH = 100;

    /** The longest subject, in bytes. */
    public const int SUBJECT_MAX_LENGTH = 191;

    private function __construct()
    {
    }

    /**
     * A scope: ASCII letters, digits and `_ . : / -`, at most 100 bytes.
     *
     * @throws InvalidSlugHistory
     */
    public static function scope(string $scope): string
    {
        if ('' === $scope || \strlen($scope) > self::SCOPE_MAX_LENGTH) {
            throw InvalidSlugHistory::because(\sprintf('A slug history scope is 1 to %d bytes long.', self::SCOPE_MAX_LENGTH));
        }

        if (1 !== preg_match('~^[A-Za-z0-9_.:/-]+$~D', $scope)) {
            throw InvalidSlugHistory::because('A slug history scope is made of ASCII letters, digits and "_ . : / -".');
        }

        return $scope;
    }

    /**
     * A subject: valid UTF-8 without control characters, at most 191 bytes.
     *
     * @throws InvalidSlugHistory
     */
    public static function subject(string $subject): string
    {
        if ('' === $subject || \strlen($subject) > self::SUBJECT_MAX_LENGTH) {
            throw InvalidSlugHistory::because(\sprintf('A slug history subject is 1 to %d bytes long.', self::SUBJECT_MAX_LENGTH));
        }

        if (1 !== preg_match('/^[^\p{Cc}]+$/Du', $subject)) {
            throw InvalidSlugHistory::because('A slug history subject is UTF-8 text without control characters.');
        }

        return $subject;
    }
}
