<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * The progress of a user could not be written: another request started the same flow for them at the same instant.
 * The next change, read afresh, succeeds.
 */
final class ProgressNotSaved extends RuntimeException implements OnboardingException
{
    public static function of(string $flow, Throwable $previous): self
    {
        return new self(\sprintf('The progress of the flow "%s" was saved by another request at the same time.', $flow), previous: $previous);
    }
}
