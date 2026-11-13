<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Exception;

use InvalidArgumentException;

/**
 * A formula cannot be converted: a command outside the supported set, a brace or an environment left open, a script
 * without a base where one is needed, or a formula longer or deeper than the limits. `$offset` is the byte of the
 * formula where the problem was found.
 */
final class InvalidLatex extends InvalidArgumentException implements LatexMathMlException
{
    public function __construct(
        string $message,
        public readonly int $offset,
    ) {
        parent::__construct($message);
    }

    public static function at(int $offset, string $problem): self
    {
        return new self(\sprintf('The formula cannot be converted at byte %d: %s.', $offset, $problem), $offset);
    }
}
