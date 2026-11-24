<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Phil\Url\Url;

/**
 * How findings quote the page: an address by its path and query, a value cut short.
 *
 * @internal
 */
final class Excerpt
{
    /** The most characters of a value a message quotes. */
    public const int LENGTH = 80;

    private function __construct()
    {
    }

    /**
     * The path and the query of an address: `/fr/` or `/search?q=1`.
     */
    public static function path(Url $url): string
    {
        return $url->path.(null === $url->query ? '' : '?'.$url->query);
    }

    /**
     * A value on one line, cut at `LENGTH` characters with an ellipsis.
     */
    public static function of(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_strlen($value) > self::LENGTH ? mb_substr($value, 0, self::LENGTH - 1).'…' : $value;
    }
}
