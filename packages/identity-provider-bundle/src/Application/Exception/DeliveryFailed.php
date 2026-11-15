<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Exception;

use Phil\IdentityProviderBundle\Domain\Exception\IdentityProviderException;
use RuntimeException;
use Throwable;

/**
 * A message the gateway did not accept: the code is not sent, and the person may ask again.
 */
final class DeliveryFailed extends RuntimeException implements IdentityProviderException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
