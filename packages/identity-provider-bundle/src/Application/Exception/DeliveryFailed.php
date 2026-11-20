<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Exception;

use Phil\IdentityProviderBundle\Domain\Exception\IdentityProviderException;
use RuntimeException;
use Throwable;

/**
 * A message the gateway or a client did not accept: a code is not sent, and the person may ask again; a logout token is
 * not delivered, and Messenger tries again.
 */
final class DeliveryFailed extends RuntimeException implements IdentityProviderException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
