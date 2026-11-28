<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application\Exception;

use RuntimeException;

/**
 * A waiting list or an entry that does not exist.
 */
final class NotFound extends RuntimeException
{
    public static function waitlist(string $id): self
    {
        return new self(\sprintf('No waiting list "%s".', mb_substr($id, 0, 64)));
    }

    public static function entry(string $id): self
    {
        return new self(\sprintf('No waiting list entry "%s".', mb_substr($id, 0, 64)));
    }
}
