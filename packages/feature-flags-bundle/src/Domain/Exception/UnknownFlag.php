<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use RuntimeException;

/**
 * A toggle named a flag that the writable store does not hold. A toggle never creates a flag: a flag is
 * declared with the feature it guards.
 */
final class UnknownFlag extends RuntimeException implements DomainError
{
    public static function named(FlagName $flag): self
    {
        return new self(\sprintf('No flag named "%s" can be toggled.', $flag->value));
    }
}
