<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Exception;

use Phil\OnboardingBundle\Domain\Exception\OnboardingException;
use RuntimeException;

/**
 * No flow is declared with this identifier, or no step of the flow.
 */
final class UnknownFlow extends RuntimeException implements OnboardingException
{
    public static function named(string $flow): self
    {
        return new self(\sprintf('No onboarding flow is declared as "%s".', $flow));
    }

    public static function step(string $flow, string $step): self
    {
        return new self(\sprintf('The onboarding flow "%s" has no step "%s".', $flow, $step));
    }
}
