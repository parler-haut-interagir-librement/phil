<?php

declare(strict_types=1);

namespace Phil\Highlight\Exception;

use InvalidArgumentException;

/**
 * Line ranges written in a way `LineRanges::parse()` does not read: something other than numbers, dashes, commas and
 * spaces, a range that ends before it starts, a line 0, or more than the bounds allow; or a first line number out of
 * those bounds.
 */
final class InvalidLineRanges extends InvalidArgumentException implements HighlightException
{
    public static function syntax(string $ranges): self
    {
        return new self(\sprintf('"%s" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".', self::excerpt($ranges)));
    }

    public static function reversed(int $from, int $to): self
    {
        return new self(\sprintf('The range %d-%d ends before it starts.', $from, $to));
    }

    public static function tooMany(int $maximum): self
    {
        return new self(\sprintf('Line ranges hold %d ranges at most.', $maximum));
    }

    public static function tooLarge(int $maximum): self
    {
        return new self(\sprintf('A line number is %d at most.', $maximum));
    }

    public static function firstNumber(int $maximum): self
    {
        return new self(\sprintf('The first line number is between 0 and %d.', $maximum));
    }

    private static function excerpt(string $ranges): string
    {
        return \strlen($ranges) > 64 ? substr($ranges, 0, 64).'…' : $ranges;
    }
}
