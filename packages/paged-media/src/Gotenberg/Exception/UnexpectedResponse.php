<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use Throwable;

/**
 * A success whose body is not what the route answers: JSON of another shape, or no JSON at all.
 */
final class UnexpectedResponse extends GotenbergFailure
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 200, null, $previous);
    }
}
