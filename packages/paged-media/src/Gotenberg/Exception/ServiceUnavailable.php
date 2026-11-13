<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use Override;
use Throwable;

/**
 * Gotenberg out of reach, its queue full (429), or a request longer than its time limit (503): the same request may
 * succeed later.
 */
final class ServiceUnavailable extends GotenbergFailure
{
    public static function unreachable(string $url, Throwable $previous): self
    {
        return new self(\sprintf('Gotenberg could not be reached at %s: %s', $url, $previous->getMessage()), 0, null, $previous);
    }

    #[Override]
    public function isRetryable(): bool
    {
        return true;
    }
}
