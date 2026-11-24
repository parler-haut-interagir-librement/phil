<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;

/**
 * The checks every text and code of the reviews goes through: valid UTF-8, no control character but the line breaks
 * and tabs of a text, a length counted in characters, the spaces around trimmed.
 */
final class Texts
{
    /** A code: a criterion, a superlative, a removal criterion. */
    public const string CODE = '/^[a-z][a-z0-9_-]{0,63}$/D';

    private function __construct()
    {
    }

    /**
     * The text trimmed, or `null` when nothing is left.
     *
     * @throws InvalidReview when the text is not valid UTF-8, holds a control character or is longer than the maximum
     */
    public static function optional(?string $text, int $maximum, string $what): ?string
    {
        if (null === $text) {
            return null;
        }

        if (!mb_check_encoding($text, 'UTF-8') || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            throw InvalidReview::because(\sprintf('%s is text without control characters.', $what));
        }

        $text = trim($text);

        if ('' === $text) {
            return null;
        }

        if (mb_strlen($text) > $maximum) {
            throw InvalidReview::because(\sprintf('%s holds %d characters at most.', $what, $maximum));
        }

        return $text;
    }

    /**
     * @throws InvalidReview when the text is empty, or refused as `optional()` refuses it
     */
    public static function required(string $text, int $maximum, string $what): string
    {
        return self::optional($text, $maximum, $what) ?? throw InvalidReview::because(\sprintf('%s is not empty.', $what));
    }

    /**
     * @throws InvalidReview when the code is not a lowercase letter, then up to 63 lowercase letters, digits, "-" and "_"
     */
    public static function code(string $code, string $what): string
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidReview::because(\sprintf('"%s" is not %s: a lowercase letter, then up to 63 lowercase letters, digits, "-" and "_".', $code, $what));
        }

        return $code;
    }

    /**
     * @throws InvalidReview when the identifier is empty, longer than the maximum in bytes, or holds a space or a control character
     */
    public static function identifier(string $value, int $maximum, string $what): string
    {
        if ('' === $value || \strlen($value) > $maximum || !mb_check_encoding($value, 'UTF-8') || 1 === preg_match('/[\x00-\x20\x7F]/', $value)) {
            throw InvalidReview::because(\sprintf('%s is 1 to %d bytes of visible characters.', $what, $maximum));
        }

        return $value;
    }
}
