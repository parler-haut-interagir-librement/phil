<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;
use Throwable;

/**
 * A SAML message refused by a check of the profile: its root, its version, its issuer, its destination, its audience, its times, the request it answers or its subject.
 */
final class InvalidMessage extends RuntimeException implements SamlException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
