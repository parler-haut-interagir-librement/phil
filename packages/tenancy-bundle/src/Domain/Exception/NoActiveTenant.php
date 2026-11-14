<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * The active tenant was asked for when the resolution did not produce one.
 */
final class NoActiveTenant extends DomainException implements DomainError
{
    public static function selectionRequired(): self
    {
        return new self('No tenant is active: the account can act in several and has not chosen one.');
    }

    public static function noneReachable(): self
    {
        return new self('No tenant is active: the account cannot act in any tenant.');
    }

    public static function outsideATenant(): self
    {
        return new self('No tenant is active in this request, message or command.');
    }
}
