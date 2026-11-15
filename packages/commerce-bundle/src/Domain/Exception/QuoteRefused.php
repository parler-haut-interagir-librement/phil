<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A quote that cannot be issued, accepted or declined.
 */
final class QuoteRefused extends DomainException implements DomainError
{
    public static function unknown(string $orderNumber): self
    {
        return new self(\sprintf('No quote is known for the order %s.', $orderNumber));
    }

    public static function notPending(string $orderNumber): self
    {
        return new self(\sprintf('The quote of %s was already answered.', $orderNumber));
    }

    public static function expired(string $orderNumber): self
    {
        return new self(\sprintf('The quote of %s has expired.', $orderNumber));
    }

    public static function receiptIssued(string $orderNumber): self
    {
        return new self(\sprintf('The order %s has its receipt: it no longer changes; credit it instead.', $orderNumber));
    }
}
