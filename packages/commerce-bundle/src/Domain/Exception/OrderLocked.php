<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A change an order in its state no longer allows: lines and adjustments change only in a cart, draws are recorded
 * only on a placed order, and a state moves only along its transitions.
 */
final class OrderLocked extends DomainException implements DomainError
{
    public static function notACart(string $number, OrderState $state): self
    {
        return new self(\sprintf('The order %s is %s: its lines and adjustments no longer change.', $number, $state->value));
    }

    public static function notPlaced(string $number, OrderState $state): self
    {
        return new self(\sprintf('The order %s is %s: its draws are made once it is placed and paid.', $number, $state->value));
    }

    public static function transition(string $number, OrderState $from, OrderState $to): self
    {
        return new self(\sprintf('The order %s is %s: it cannot become %s.', $number, $from->value, $to->value));
    }

    public static function empty(string $number): self
    {
        return new self(\sprintf('The order %s has no line: an empty cart cannot be placed.', $number));
    }

    public static function status(string $number, OrderState $state, string $status, OrderState $attachedTo): self
    {
        return new self(\sprintf('The order %s is %s: the status "%s" belongs to %s orders.', $number, $state->value, $status, $attachedTo->value));
    }

    public static function forcingRefused(string $number, OrderState $from, OrderState $to): self
    {
        return new self(\sprintf('The order %s is %s: nobody here may force it to %s.', $number, $from->value, $to->value));
    }

    public static function blocked(string $number, OrderState $state, string $transition): self
    {
        return new self(\sprintf('The order %s is %s: the transition "%s" is not open to it.', $number, $state->value, $transition));
    }
}
