<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DateTimeImmutable;
use DomainException;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Deferred\DeferredSaleState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A deferred sale that cannot be opened, called or cancelled.
 */
final class DeferredSaleRefused extends DomainException implements DomainError
{
    public static function unknown(string $orderNumber): self
    {
        return new self(\sprintf('No deferred sale is known for the order %s.', $orderNumber));
    }

    public static function alreadyDeferred(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is already paid later: an order has one deferred sale.', $orderNumber));
    }

    public static function orderNotPlaced(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: only a placed order is paid later.', $orderNumber));
    }

    public static function nothingToPay(string $orderNumber): self
    {
        return new self(\sprintf('The order %s has nothing to pay: nothing is deferred.', $orderNumber));
    }

    public static function noDueDate(string $orderNumber): self
    {
        return new self(\sprintf('The balance of %s is called by a date, and none is given or counted.', $orderNumber));
    }

    public static function dueDateWithoutDateTrigger(string $orderNumber, BalanceTrigger $trigger): self
    {
        return new self(\sprintf('The balance of %s is called by %s: it has no due date.', $orderNumber, $trigger->value));
    }

    public static function dueBeforeOpening(string $orderNumber): self
    {
        return new self(\sprintf('The balance of %s is due after the sale opens.', $orderNumber));
    }

    public static function otherTrigger(string $orderNumber, BalanceTrigger $expected, BalanceTrigger $given): self
    {
        return new self(\sprintf('The balance of %s is called by %s, not by %s.', $orderNumber, $expected->value, $given->value));
    }

    public static function notDue(string $orderNumber, DateTimeImmutable $dueAt): self
    {
        return new self(\sprintf('The balance of %s is not due before %s.', $orderNumber, $dueAt->format(\DATE_ATOM)));
    }

    public static function state(string $orderNumber, DeferredSaleState $state, string $action): self
    {
        return new self(\sprintf('The deferred sale of %s is %s: it cannot %s.', $orderNumber, str_replace('_', ' ', $state->value), $action));
    }
}
