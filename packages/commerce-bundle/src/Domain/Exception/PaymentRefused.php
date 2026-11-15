<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A payment that cannot be started, refunded or moved as asked.
 */
final class PaymentRefused extends DomainException implements DomainError
{
    public static function orderNotPlaced(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: only a placed order is paid.', $orderNumber));
    }

    public static function amount(string $what, string $amount, string $limit): self
    {
        return new self(\sprintf('%s of %s is refused: it must be above zero and at most %s.', $what, $amount, $limit));
    }

    public static function event(string $paymentId, PaymentState $state, PaymentEvent $event): self
    {
        return new self(\sprintf('The payment %s is %s: a "%s" notification does not apply to it.', $paymentId, $state->value, $event->value));
    }

    public static function notRefundable(string $paymentId, PaymentState $state): self
    {
        return new self(\sprintf('The payment %s is %s: only a completed payment is refunded.', $paymentId, $state->value));
    }

    public static function unknownMethod(string $method): self
    {
        return new self(\sprintf('No payment gateway supports the method "%s".', $method));
    }

    public static function notAbandonable(string $paymentId, PaymentState $state): self
    {
        return new self(\sprintf('The payment %s is %s: only a new or processing payment is abandoned.', $paymentId, $state->value));
    }

    public static function currency(string $paymentId, string $expected, string $given): self
    {
        return new self(\sprintf('The payment %s is in %s: a notification in %s does not apply to it.', $paymentId, $expected, $given));
    }

    public static function alreadyStarted(string $paymentId): self
    {
        return new self(\sprintf('The payment %s was already handed to its processor.', $paymentId));
    }

    public static function offline(string $paymentId, PaymentState $state, string $what): self
    {
        return new self(\sprintf('The payment %s is %s: only an offline payment awaiting it is %s by hand.', $paymentId, $state->value, $what));
    }

    public static function notOfflineMethod(string $method): self
    {
        return new self(\sprintf('"%s" is not a method of phil_commerce.offline_payments.', $method));
    }

    public static function refundSettled(string $refundId, string $state): self
    {
        return new self(\sprintf('The refund %s is %s: only a granted refund is executed or withdrawn.', $refundId, $state));
    }
}
