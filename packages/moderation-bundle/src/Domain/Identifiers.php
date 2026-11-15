<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain;

use Phil\ModerationBundle\Domain\Exception\InvalidModeration;

/**
 * The identifiers of the records of the bundle — 32 lowercase hexadecimal characters, random — and the names of its
 * codes: a lowercase letter, then up to 63 lowercase letters, digits, hyphens and underscores.
 */
final class Identifiers
{
    public const int LENGTH = 32;

    public const string CODE = '/^[a-z][a-z0-9_-]{0,63}$/D';

    private function __construct()
    {
    }

    public static function generate(): string
    {
        return sodium_bin2hex(random_bytes(16));
    }

    public static function check(string $id, string $what): string
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidModeration::because(\sprintf('%s is identified by 32 lowercase hexadecimal characters.', $what));
        }

        return $id;
    }

    public static function code(string $code, string $what): string
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidModeration::because(\sprintf('"%s" is not %s: a lowercase letter, then up to 63 lowercase letters, digits, hyphens and underscores.', $code, $what));
        }

        return $code;
    }

    /**
     * The text trimmed, `null` when it is empty, refused when it is longer than the limit.
     */
    public static function text(?string $text, int $limit, string $what): ?string
    {
        $text = null === $text ? '' : trim($text);

        if (mb_strlen($text) > $limit) {
            throw InvalidModeration::because(\sprintf('%s has at most %d characters.', $what, $limit));
        }

        return '' === $text ? null : $text;
    }
}
