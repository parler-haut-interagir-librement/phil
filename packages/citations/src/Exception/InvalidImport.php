<?php

declare(strict_types=1);

namespace Phil\Citations\Exception;

use InvalidArgumentException;

/**
 * BibTeX or RIS that cannot be read: a syntax error, an undefined `@string`, or an input beyond the limits of size,
 * entries, field length or nesting. `$inputLine` is the line of the input where the problem was found, from 1.
 */
final class InvalidImport extends InvalidArgumentException implements CitationsException
{
    public function __construct(
        string $message,
        public readonly int $inputLine,
    ) {
        parent::__construct($message);
    }

    public static function at(string $format, int $line, string $problem): self
    {
        return new self(\sprintf('The %s cannot be read at line %d: %s.', $format, $line, $problem), $line);
    }
}
