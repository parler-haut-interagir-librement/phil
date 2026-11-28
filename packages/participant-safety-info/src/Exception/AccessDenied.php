<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Exception;

use RuntimeException;

/**
 * A reading or a submission the rules refuse. The message is the same whatever the reason, so that it tells nothing
 * of the roles, the window or the record.
 */
final class AccessDenied extends RuntimeException implements ParticipantSafetyInfoException
{
    public static function create(): self
    {
        return new self('This safety information is not available to you.');
    }
}
