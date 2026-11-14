<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use RuntimeException;

/**
 * The client sent more requests without an account than its limit allows: nothing was sent, and it can try again after
 * the time the exception gives.
 */
final class TooManyGuestRequests extends RuntimeException implements PrivacyError
{
    private int $retryAfter = 0;

    public static function retryIn(int $seconds): self
    {
        $exception = new self('Too many requests from this client.');
        $exception->retryAfter = max(1, $seconds);

        return $exception;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
