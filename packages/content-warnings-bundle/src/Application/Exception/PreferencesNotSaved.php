<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Application\Exception;

use RuntimeException;

/**
 * Warning preferences that could not be saved: no request, no session, no member.
 */
final class PreferencesNotSaved extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The warning preferences cannot be saved: %s.', rtrim($reason, '.')));
    }
}
