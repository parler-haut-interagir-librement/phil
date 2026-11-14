<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A progress that cannot be kept: an empty user identifier, or one longer than its column.
 */
final class InvalidProgress extends InvalidArgumentException implements OnboardingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
