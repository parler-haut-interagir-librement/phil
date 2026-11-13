<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use InvalidArgumentException;

/**
 * A stage built with options Gotenberg would refuse: a page selection, an angle, a colour or a size out of range, a missing option, or two options that exclude each other.
 */
final class InvalidStage extends InvalidArgumentException implements GotenbergException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
