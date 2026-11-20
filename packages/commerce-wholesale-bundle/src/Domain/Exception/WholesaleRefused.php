<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A change of groups the module refuses.
 */
final class WholesaleRefused extends DomainException implements DomainError
{
    public static function unknownGroup(string $code): self
    {
        return new self(\sprintf('No customer group is known as "%s".', $code));
    }

    public static function noAccount(string $buyer): self
    {
        return new self(\sprintf('%s has no open credit account.', $buyer));
    }

    public static function overLimit(string $buyer, string $amount, string $available): self
    {
        return new self(\sprintf('%s on account goes beyond the credit of %s: %s is available.', $amount, $buyer, $available));
    }

    public static function unknownReceivable(string $id): self
    {
        return new self(\sprintf('No receivable is known as %s.', $id));
    }
}
