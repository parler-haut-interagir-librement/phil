<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use RuntimeException;

/**
 * A login initiation, an authentication request or a launch whose parameters are missing, malformed or do not match
 * what was registered or remembered.
 */
final class InvalidRequest extends RuntimeException implements LtiException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
