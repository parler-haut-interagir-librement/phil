<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Exception;

use Phil\NotificationsBundle\Domain\Exception\NotificationsException;
use RuntimeException;

/**
 * A notification of a type the configuration does not declare.
 */
final class UnknownNotificationType extends RuntimeException implements NotificationsException
{
    public static function named(string $type): self
    {
        return new self(\sprintf('The notification type "%s" is not declared under "phil_notifications.types".', $type));
    }
}
