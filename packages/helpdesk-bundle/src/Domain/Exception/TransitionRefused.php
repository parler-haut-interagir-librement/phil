<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * A change of status the workflow of the requests does not allow from where the request stands, or that a guard of the
 * application held.
 */
final class TransitionRefused extends RuntimeException implements HelpdeskException
{
    public static function of(string $transition, string $status, ?Throwable $previous = null): self
    {
        return new self(\sprintf('A request that is %s cannot be changed by "%s".', $status, $transition), previous: $previous);
    }
}
