<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Exception;

use InvalidArgumentException;

/**
 * Text that is not a code of the format: too long, of the wrong length, holding a symbol outside the alphabet, or
 * failing its check symbol. The message never repeats the text.
 */
final class InvalidCode extends InvalidArgumentException implements AccessCodesException
{
    public static function tooLong(int $length, int $max): self
    {
        return new self(\sprintf('A code is at most %d characters as typed; %d were given.', $max, $length));
    }

    public static function length(int $length, int $expected): self
    {
        return new self(\sprintf('A code has %d symbols; %d were given.', $expected, $length));
    }

    public static function symbol(): self
    {
        return new self('The code holds a symbol outside its alphabet.');
    }

    public static function checkSymbol(): self
    {
        return new self('The check symbol of the code does not match: a symbol was mistyped.');
    }
}
