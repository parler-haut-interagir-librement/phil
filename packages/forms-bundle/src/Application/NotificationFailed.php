<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

use Phil\FormsBundle\Domain\Exception\FormsException;
use RuntimeException;
use Throwable;

/**
 * An entry nobody heard of through one channel. The entry is kept all the same.
 */
final class NotificationFailed extends RuntimeException implements FormsException
{
    public static function because(string $channel, Throwable $previous): self
    {
        return new self(\sprintf('The %s notification failed: %s', $channel, $previous->getMessage()), previous: $previous);
    }
}
