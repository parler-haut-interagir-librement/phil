<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Exception;

use Phil\NotificationsBundle\Domain\Exception\NotificationsException;
use RuntimeException;

/**
 * An audience no audience provider supports.
 */
final class UnknownAudience extends RuntimeException implements NotificationsException
{
    public static function named(string $audience): self
    {
        return new self(\sprintf('No audience provider supports the audience "%s".', $audience));
    }
}
