<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * An answer a question cannot take: nothing picked, a choice it does not offer or picked twice, several where it asks
 * for one. The message names no choice, since the answer comes from a request.
 */
final class InvalidAnswer extends InvalidArgumentException implements OnboardingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
