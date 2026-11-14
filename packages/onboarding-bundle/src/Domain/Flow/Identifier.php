<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;

/**
 * The identifiers of flows and steps: a lowercase letter or a digit, then up to 63 lowercase letters, digits, `_`, `.`
 * and `-`. They go in URLs and table columns as they are.
 */
final class Identifier
{
    public const string PATTERN = '[a-z0-9][a-z0-9_.-]{0,63}';

    /**
     * @throws InvalidFlow when the identifier is malformed
     */
    public static function check(string $identifier, string $what): string
    {
        if (1 !== preg_match('/^'.self::PATTERN.'$/D', $identifier)) {
            throw InvalidFlow::because(\sprintf('The %s "%s" is not made of up to 64 lowercase letters, digits, "_", "." and "-", starting with a letter or a digit.', $what, $identifier));
        }

        return $identifier;
    }

    /**
     * A text of the declaration — a translation key or the text itself — that is neither empty, nor longer than its
     * bound, nor holding a control character.
     *
     * @throws InvalidFlow when the text breaks one of these
     */
    public static function text(string $text, string $what, int $max): string
    {
        if ('' === trim($text) || mb_strlen($text) > $max || 1 === preg_match('/[\x00-\x1F\x7F]/', $text)) {
            throw InvalidFlow::because(\sprintf('The %s "%s" is empty, longer than %d characters or holds a control character.', $what, $text, $max));
        }

        return $text;
    }
}
