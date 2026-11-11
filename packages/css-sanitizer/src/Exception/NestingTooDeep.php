<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Exception;

use InvalidArgumentException;

/**
 * The parser met blocks and functions nested deeper than it allows. The sanitizer catches it and refuses the whole
 * source; the parser, used on its own, lets it through.
 */
final class NestingTooDeep extends InvalidArgumentException implements CssSanitizerException
{
    public static function beyond(int $depth, int $line): self
    {
        return new self(\sprintf('The CSS nests blocks and functions deeper than %d levels, on line %d.', $depth, $line));
    }
}
