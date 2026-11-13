<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use RuntimeException;
use Throwable;

/**
 * A signing certificate its source could not give: a vault out of reach, a secret missing or of another shape.
 */
final class CertificateUnavailable extends RuntimeException implements GotenbergException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
