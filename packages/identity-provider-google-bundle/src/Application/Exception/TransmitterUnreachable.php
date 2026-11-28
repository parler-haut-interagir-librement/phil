<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application\Exception;

use RuntimeException;
use Throwable;

/**
 * The keys of a transmitter could not be read, so a security event token could not be checked: the transmitter sends
 * it again later.
 */
final class TransmitterUnreachable extends RuntimeException
{
    public static function because(Throwable $previous): self
    {
        return new self('The keys of the transmitter could not be read.', previous: $previous);
    }
}
