<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use RuntimeException;

/**
 * The application's authorizer refused a toggle: missing permission, missing stronger confirmation.
 */
final class ToggleRefused extends RuntimeException implements DomainError
{
    public static function forFlag(FlagName $flag, string $why): self
    {
        return new self(\sprintf('Toggling flag "%s" is refused: %s', $flag->value, $why));
    }
}
