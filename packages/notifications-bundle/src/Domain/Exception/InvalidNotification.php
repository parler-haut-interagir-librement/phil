<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A notification, or one of its parts, that breaks a rule: an identifier of the wrong shape, data that is not made of
 * identifiers, a stored row that is not a notification.
 */
final class InvalidNotification extends InvalidArgumentException implements NotificationsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
