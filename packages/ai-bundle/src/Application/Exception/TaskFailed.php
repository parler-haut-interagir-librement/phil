<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Exception;

use Phil\AiBundle\Domain\Exception\AiException;
use RuntimeException;
use Throwable;

/**
 * The call to the model failed: the provider refused or did not answer. The failure is in the journal; the provider's
 * exception is the previous one, and its message is not repeated, since a provider may quote the prompt in it.
 */
final class TaskFailed extends RuntimeException implements AiException
{
    public static function of(string $kind, Throwable $previous): self
    {
        return new self(\sprintf('The task "%s" failed at the model (%s).', $kind, $previous::class), previous: $previous);
    }
}
