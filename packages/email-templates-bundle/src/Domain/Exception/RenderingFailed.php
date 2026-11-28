<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * A template that could not be rendered: an error of its source at run time, a call the sandbox refuses, an output
 * larger than the ceiling, or an embedded image that cannot be read.
 */
final class RenderingFailed extends RuntimeException implements EmailTemplatesException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
