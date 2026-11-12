<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Domain\Exception;

use LogicException;

/**
 * No handler applies this transition to this resource: the application registers a `TransitionHandler` for it.
 */
final class NoHandler extends LogicException implements SchedulerException
{
    public static function for(string $resource, string $transition): self
    {
        return new self(\sprintf('No handler applies "%s" to %s: register a TransitionHandler for it.', $transition, $resource));
    }
}
