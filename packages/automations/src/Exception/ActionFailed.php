<?php

declare(strict_types=1);

namespace Phil\Automations\Exception;

use RuntimeException;
use Throwable;

/**
 * What an action throws when it could not do its work. A retryable failure is tried again as many times as the step
 * allows; a permanent one fails the run at once, and the steps before it are compensated.
 *
 * Any other exception an action throws counts as retryable.
 */
final class ActionFailed extends RuntimeException implements AutomationsException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function retryable(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, true, $previous);
    }

    public static function permanent(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, false, $previous);
    }
}
