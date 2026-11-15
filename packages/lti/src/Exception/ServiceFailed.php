<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use RuntimeException;
use Throwable;

/**
 * A call to a service of the other side — its key set, its token endpoint, its grades or its roster — that could not
 * be made, or that answered with an error.
 */
final class ServiceFailed extends RuntimeException implements LtiException
{
    public static function because(string $reason, ?Throwable $previous = null, int $status = 0): self
    {
        return new self($reason, $status, $previous);
    }

    /**
     * The HTTP status the service answered, or 0 when it did not answer.
     */
    public function status(): int
    {
        return $this->getCode();
    }
}
