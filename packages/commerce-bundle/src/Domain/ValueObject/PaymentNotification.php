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
 *
 * An ignored notification — a verified event the store does not act on — names no payment: it carries the
 * processor's type of the event instead, which a webhook logs before acknowledging it.
 */
final readonly class PaymentNotification
{
    private const string VISIBLE_ASCII = '/^[\x21-\x7E]{1,255}$/D';

    /**
     * @param string|null $externalReference the processor's reference of the payment; null for an ignored notification only
     * @param string|null $ignoredType       the processor's type of an ignored event; null for every other notification
     */
    private function __construct(
        public string $id,
        public ?string $externalReference,
        public PaymentEvent $event,
        public ?Money $amount,
        public ?string $ignoredType = null,
    ) {
    }

    public static function of(string $id, string $externalReference, PaymentEvent $event, ?Money $amount = null): self
    {
        if (1 !== preg_match(self::VISIBLE_ASCII, $id) || 1 !== preg_match(self::VISIBLE_ASCII, $externalReference)) {
            throw InvalidValue::because('A notification id and a payment reference are 1 to 255 visible ASCII characters.');
        }

        if (PaymentEvent::Ignored === $event) {
            throw InvalidValue::because('An ignored notification names no payment: it is made by ignored().');
        }

        if ((PaymentEvent::Refunded === $event) !== ($amount instanceof Money)) {
            throw InvalidValue::because('A refund notification carries the amount refunded, and only a refund notification does.');
        }

        return new self($id, $externalReference, $event, $amount);
    }

    /**
     * A verified notification of an event the store does not act on, so that the webhook acknowledges it and the
     * processor stops sending it, rather than refusing it as unreadable.
     *
     * @param string $id   the processor's own id of the notification
     * @param string $type the processor's type of the event, such as `customer.created`
     */
    public static function ignored(string $id, string $type): self
    {
        if (1 !== preg_match(self::VISIBLE_ASCII, $id) || 1 !== preg_match(self::VISIBLE_ASCII, $type)) {
            throw InvalidValue::because('A notification id and an event type are 1 to 255 visible ASCII characters.');
        }

        return new self($id, null, PaymentEvent::Ignored, null, $type);
    }

    public function isIgnored(): bool
    {
        return PaymentEvent::Ignored === $this->event;
    }
}
