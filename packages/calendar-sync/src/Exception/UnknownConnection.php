<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Exception;

use RuntimeException;

/**
 * A connection the repository does not know, or one whose adapter is not registered for what was asked.
 */
final class UnknownConnection extends RuntimeException implements CalendarSyncException
{
    public static function id(string $id): self
    {
        return new self(\sprintf('No calendar connection "%s".', mb_substr($id, 0, 100)));
    }

    /**
     * @param string $role what the adapter was asked for: `to read busy times from`, `to write events to`
     */
    public static function provider(string $provider, string $role): self
    {
        return new self(\sprintf('No calendar adapter "%s" %s.', mb_substr($provider, 0, 100), $role));
    }
}
