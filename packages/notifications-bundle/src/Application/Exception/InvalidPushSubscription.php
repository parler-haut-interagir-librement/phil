<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Exception;

use InvalidArgumentException;
use Phil\NotificationsBundle\Domain\Exception\NotificationsException;

/**
 * A push subscription a browser sent that cannot be kept: not the shape of `PushSubscription.toJSON()`, an endpoint
 * that is no HTTPS address of a public host or of an allowed push service, or keys of the wrong length.
 */
final class InvalidPushSubscription extends InvalidArgumentException implements NotificationsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
