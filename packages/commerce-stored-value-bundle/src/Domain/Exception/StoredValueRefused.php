<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A gift card or a store credit refused an operation.
 */
final class StoredValueRefused extends DomainException implements DomainError
{
    public static function unknownCard(): self
    {
        return new self('No gift card is known by that code.');
    }

    public static function inactive(string $id): self
    {
        return new self(\sprintf('The stored value %s is voided.', $id));
    }

    public static function expired(string $id): self
    {
        return new self(\sprintf('The stored value %s has expired.', $id));
    }

    public static function insufficient(string $id, string $wanted, string $balance): self
    {
        return new self(\sprintf('The stored value %s holds %s; %s is more.', $id, $balance, $wanted));
    }

    public static function noCredit(string $holder, string $currency): self
    {
        return new self(\sprintf('%s has no store credit in %s.', $holder, $currency));
    }

    public static function notRedeemed(string $payment): self
    {
        return new self(\sprintf('The payment %s spent no stored value.', $payment));
    }

    public static function notPaid(string $orderNumber, string $payable): self
    {
        return new self(\sprintf('The order %s still has %s to pay: its gift cards are issued once it is paid.', $orderNumber, $payable));
    }

    public static function noRefundHolder(string $payment, string $instrument): self
    {
        return new self(\sprintf('The payment %s cannot go back to the stored value %s, voided or expired, and no customer is known to take it as store credit.', $payment, $instrument));
    }
}
