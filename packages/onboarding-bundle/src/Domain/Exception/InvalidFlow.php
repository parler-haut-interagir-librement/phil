<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A declaration of a flow or of a step the bundle cannot use — a malformed identifier, a step twice, a completion
 * without what it waits for, an order in a circle. A mistake of the code, found when the container is built.
 */
final class InvalidFlow extends InvalidArgumentException implements OnboardingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
