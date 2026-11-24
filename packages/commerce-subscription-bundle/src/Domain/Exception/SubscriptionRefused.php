<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Exception;

use DomainException;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A subscription that cannot start, renew, change or end as asked.
 */
final class SubscriptionRefused extends DomainException implements DomainError
{
    public static function unknownOffer(string $code): self
    {
        return new self(\sprintf('No offer is known as "%s".', $code));
    }

    public static function withdrawn(string $code): self
    {
        return new self(\sprintf('The offer "%s" is withdrawn: nobody subscribes to it any more.', $code));
    }

    public static function state(string $id, SubscriptionState $state, string $what): self
    {
        return new self(\sprintf('The subscription %s is %s: it cannot %s.', $id, $state->value, $what));
    }

    public static function notTheOrder(string $id, string $orderNumber): self
    {
        return new self(\sprintf('The subscription %s awaits no payment of the order %s.', $id, $orderNumber));
    }

    public static function otherFamily(string $code, string $family): self
    {
        return new self(\sprintf('The offer "%s" is not of the family "%s": a subscription changes tier within its family.', $code, $family));
    }

    public static function otherCurrency(string $code, string $currency): self
    {
        return new self(\sprintf('The offer "%s" is not priced in %s, the currency of the subscription.', $code, $currency));
    }

    public static function periodOver(string $id): self
    {
        return new self(\sprintf('The period of the subscription %s is over: it changes tier with its renewal.', $id));
    }

    public static function withoutSeats(string $code): self
    {
        return new self(\sprintf('The offer "%s" counts no seats: every member of the organization uses it.', $code));
    }

    public static function seatsTaken(string $id, int $seats): self
    {
        return new self(\sprintf('The %d seats of the subscription %s are taken.', $seats, $id));
    }

    public static function notDue(string $id): self
    {
        return new self(\sprintf('The subscription %s is not due for an order.', $id));
    }

    public static function phoneRequired(string $method): self
    {
        return new self(\sprintf('The carrier of the delivery method "%s" asks for a telephone number.', $method));
    }
}
