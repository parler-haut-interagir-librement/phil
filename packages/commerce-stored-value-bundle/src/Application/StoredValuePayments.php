<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceStoredValueBundle\Domain\Exception\StoredValueRefused;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\Money\Money;

/**
 * Pays part or all of an order with a gift card or store credit, as one payment of the core — beside a card or cash,
 * for a split payment. The payment is created, the value spent and the payment completed at once, in the same
 * transaction: nothing waits for a processor.
 */
final readonly class StoredValuePayments
{
    public function __construct(
        private Payments $payments,
        private InstrumentRepository $instruments,
        private GiftCards $giftCards,
    ) {
    }

    /**
     * @param Money|null $amount what to spend; by default, as much of what is left to pay as the card holds
     */
    public function payWithGiftCard(Order $order, string $code, DateTimeImmutable $at, ?Money $amount = null): Payment
    {
        return $this->pay($order, $this->giftCards->find($code), StoredValueGateway::GIFT_CARD, $at, $amount);
    }

    /**
     * @param Money|null $amount what to spend; by default, as much of what is left to pay as the credit holds
     */
    public function payWithStoreCredit(Order $order, string $holder, DateTimeImmutable $at, ?Money $amount = null): Payment
    {
        $credit = $this->instruments->findStoreCredit($holder, $order->currency->code) ?? throw StoredValueRefused::noCredit($holder, $order->currency->code);

        return $this->pay($order, $credit, StoredValueGateway::STORE_CREDIT, $at, $amount);
    }

    private function pay(Order $order, Instrument $instrument, string $method, DateTimeImmutable $at, ?Money $amount): Payment
    {
        if (!$amount instanceof Money) {
            $payable = $this->payments->summary($order)->payable();
            $amount = $payable->isGreaterThan($instrument->balance) ? $instrument->balance : $payable;
        }

        // Every refusal of the instrument — voided, expired, nothing to spend, more than it holds — is checked before
        // the payment exists, so that a refusal leaves nothing behind.
        $instrument->assertRedeemable($amount, $at);

        [$payment] = $this->payments->start($order, $method, $at, $amount);
        $redemption = $instrument->redeem($amount, $at, $order->number, $payment->id);
        $this->instruments->addMovement($redemption);
        $payment->apply(PaymentNotification::of($redemption->id, $payment->id, PaymentEvent::Captured));

        return $payment;
    }
}
