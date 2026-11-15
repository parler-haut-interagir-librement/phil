<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * The draws of an order cannot be made: it is not paid yet, or no `OptionDrawer` supports the drawer an option names.
 */
final class DrawRefused extends DomainException implements DomainError
{
    public static function notPaid(string $number, OrderPaymentState $state): self
    {
        return new self(\sprintf('The order %s is %s: its options are drawn once it is paid.', $number, $state->value));
    }

    public static function noDrawer(string $drawer): self
    {
        return new self(\sprintf('No option drawer supports the drawer "%s".', $drawer));
    }
}
