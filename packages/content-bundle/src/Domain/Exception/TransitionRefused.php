<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use RuntimeException;

/**
 * The editorial workflow does not allow the transition from where the row stands: publishing what is already
 * published, removing a draft that does not exist or whose shared values other locales read, or a guard of the application saying no.
 */
final class TransitionRefused extends RuntimeException implements DomainError
{
    public static function from(string $transition, ?string $place, string $dimension): self
    {
        return new self(\sprintf('The transition "%s" is not allowed from the place "%s" of "%s".', $transition, $place ?? 'none', $dimension));
    }

    /**
     * @param list<string> $locales the other locales that read the shared row
     */
    public static function sharedValuesPending(string $dimension, array $locales): self
    {
        return new self(\sprintf('The draft of "%s" cannot be removed: its shared values changed since it was published, and the locales "%s" read them too.', $dimension, implode('", "', $locales)));
    }

    public static function withoutWorkflow(string $class): self
    {
        return new self(\sprintf('The row class "%s" is not WorkflowCapable, so it cannot be published.', $class));
    }
}
