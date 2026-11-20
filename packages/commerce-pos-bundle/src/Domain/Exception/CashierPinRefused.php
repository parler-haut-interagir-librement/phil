<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Exception;

use DateTimeImmutable;
use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A cashier could not take over a shared register by PIN: the switch is off, the PIN is wrong — or the cashier has
 * none, which reads the same —, or too many attempts were made.
 */
final class CashierPinRefused extends DomainException implements DomainError
{
    /** When the next attempt is taken, once too many were made. */
    public private(set) ?DateTimeImmutable $retryAfter = null;

    public static function disabled(): self
    {
        return new self('The cashier switch by PIN is not enabled.');
    }

    public static function wrongPin(): self
    {
        return new self('The PIN does not match.');
    }

    public static function tooManyAttempts(DateTimeImmutable $retryAfter): self
    {
        $refused = new self('Too many PIN attempts: try again later.');
        $refused->retryAfter = $retryAfter;

        return $refused;
    }
}
