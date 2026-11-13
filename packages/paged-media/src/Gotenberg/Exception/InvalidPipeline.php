<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use InvalidArgumentException;

/**
 * A list of stages in an order Gotenberg refuses: a stage that must come first or last elsewhere, a stage given twice, or a stage after one it may not follow.
 */
final class InvalidPipeline extends InvalidArgumentException implements GotenbergException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
