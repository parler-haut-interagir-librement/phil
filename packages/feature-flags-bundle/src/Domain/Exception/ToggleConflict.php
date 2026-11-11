<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use RuntimeException;

/**
 * The active state of a flag changed between the read of a toggle and its write, most likely under a concurrent
 * toggle: the write was not applied. The operator reads the flag again and decides anew.
 */
final class ToggleConflict extends RuntimeException implements DomainError
{
    public static function on(FlagName $flag, bool $expected): self
    {
        return new self(\sprintf('The flag "%s" is no longer %s; the toggle was not applied.', $flag->value, $expected ? 'active' : 'inactive'));
    }
}
