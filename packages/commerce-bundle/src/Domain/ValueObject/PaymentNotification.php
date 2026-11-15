<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * A processor's notification once its gateway has verified its signature and read it: the processor's own id of
 * the notification, which makes a replay harmless, the processor's reference of the payment, what happened, and
 * the amount of a refund.
 */
final readonly class PaymentNotification
{
    private function __construct(
        public string $id,
        public string $externalReference,
        public PaymentEvent $event,
        public ?Money $amount,
    ) {
    }

    public static function of(string $id, string $externalReference, PaymentEvent $event, ?Money $amount = null): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $id) || 1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $externalReference)) {
            throw InvalidValue::because('A notification id and a payment reference are 1 to 255 visible ASCII characters.');
        }

        if ((PaymentEvent::Refunded === $event) !== ($amount instanceof Money)) {
            throw InvalidValue::because('A refund notification carries the amount refunded, and only a refund notification does.');
        }

        return new self($id, $externalReference, $event, $amount);
    }
}
