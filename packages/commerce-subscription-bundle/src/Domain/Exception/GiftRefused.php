<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Exception;

use DomainException;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A gift that cannot be bought, paid, redeemed or cancelled as asked.
 */
final class GiftRefused extends DomainException implements DomainError
{
    public static function unknown(string $id): self
    {
        return new self(\sprintf('No gift is known as %s.', $id));
    }

    public static function withdrawn(string $code): self
    {
        return new self(\sprintf('The offer "%s" is withdrawn: it is not offered any more.', $code));
    }

    public static function free(string $code): self
    {
        return new self(\sprintf('The offer "%s" is free: there is nothing to offer.', $code));
    }

    public static function state(string $id, GiftState $state, string $what): self
    {
        return new self(\sprintf('The gift %s is %s: it cannot %s.', $id, $state->value, $what));
    }

    public static function notTheOrder(string $id, string $orderNumber): self
    {
        return new self(\sprintf('The gift %s awaits no payment of the order %s.', $id, $orderNumber));
    }

    /**
     * The same refusal for a code that is unknown, malformed, already used, voided or expired: nothing tells a guess
     * apart from a code that once was.
     */
    public static function notRedeemable(): self
    {
        return new self('This code redeems no gift.');
    }
}
