<?php

declare(strict_types=1);

namespace Phil\Slides\Domain\Exception;

use InvalidArgumentException;

/**
 * An option of the package out of its range: a heading level, a limit, a class name.
 */
final class InvalidOption extends InvalidArgumentException implements SlidesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
