<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Exception;

use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;
use RuntimeException;

/**
 * An OAuth token refreshed at the service whose row kept changing under other processes, and that the bundle could not
 * save after a few attempts; the task may try again.
 */
final class RefreshConflict extends RuntimeException implements UserSecretsException
{
    public static function named(string $name, int $attempts): self
    {
        return new self(\sprintf('The refreshed OAuth token "%s" was not saved: its row changed under other processes %d times.', $name, $attempts));
    }
}
