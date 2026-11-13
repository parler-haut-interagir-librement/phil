<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

/**
 * Numbers as PDF writes them: a fixed number of decimals at most, no trailing zero, no exponent.
 *
 * @internal
 */
final class Number
{
    public static function format(float $value, int $decimals = 2): string
    {
        $text = rtrim(rtrim(\sprintf('%.'.$decimals.'F', $value), '0'), '.');

        return '-0' === $text ? '0' : $text;
    }
}
