<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\CommerceStoredValueBundle\Application\Port\StoreCreditHolders;
use Phil\CommerceStoredValueBundle\Domain\Exception\StoredValueRefused;
use Phil\CommerceStoredValueBundle\Domain\Instrument\InstrumentKind;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\Money\Money;
use Psr\Clock\ClockInterface;

/**
 * The core's gateway for the methods `gift_card` and `store_credit`: its own processor. A payment's reference is its
 * id; a refund gives the value back to the instrument it was spent from and is recorded at once, under its idempotency
 * key: asked again, it gives nothing more. It receives no notification.
 *
 * A gift card voided or expired since takes no value: the refund becomes the store credit of the order's customer,
 * whom `StoreCreditHolders` names. A store credit voided since, or a card whose customer is unknown, refuses the
 * refund, which the application then makes by another means.
 */
final readonly class StoredValueGateway implements PaymentGateway
{
    public const string GIFT_CARD = 'gift_card';

    public const string STORE_CREDIT = 'store_credit';

    public function __construct(
        private InstrumentRepository $instruments,
        private ClockInterface $clock,
        private ?StoreCreditHolders $holders = null,
    ) {
    }

    #[Override]
    public function supports(string $method): bool
    {
        return self::GIFT_CARD === $method || self::STORE_CREDIT === $method;
    }

    #[Override]
    public function start(Payment $payment, Order $order): PaymentStart
    {
        return PaymentStart::of($payment->id);
    }

    #[Override]
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void
    {
        $redemption = $this->instruments->redemptionOf($payment->id) ?? throw StoredValueRefused::notRedeemed($payment->id);
        $instrument = $this->instruments->findInstrument($redemption->instrument);

        if (!$instrument instanceof Instrument) {
            throw StoredValueRefused::notRedeemed($payment->id);
        }

        $at = $this->clock->now();

        if (!$instrument->canTakeValue($at)) {
            $instrument = $this->storeCreditInstead($instrument, $payment, $at);
        }

        // The refund is recorded on the payment first: a key already applied gives no value back a second time.
        if (!$payment->apply(PaymentNotification::of($idempotencyKey, $payment->id, PaymentEvent::Refunded, $amount))) {
            return;
        }

        $this->instruments->addMovement($instrument->credit(MovementKind::Restored, $amount, $at, $payment->orderNumber, $payment->id));
    }

    /**
     * The store credit of the order's customer, opened if need be, for a refund the gift card can no longer take.
     */
    private function storeCreditInstead(Instrument $spentFrom, Payment $payment, DateTimeImmutable $at): Instrument
    {
        $holder = InstrumentKind::GiftCard === $spentFrom->kind ? $this->holders?->holderOf($payment->orderNumber) : null;

        if (null === $holder) {
            throw StoredValueRefused::noRefundHolder($payment->id, $spentFrom->id);
        }

        $currency = $payment->amount->currency;
        $credit = $this->instruments->findStoreCredit($holder, $currency->code);

        if (!$credit instanceof Instrument) {
            $credit = Instrument::storeCredit($holder, $currency, $at);
            $this->instruments->addInstrument($credit);
        }

        return $credit;
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        throw InvalidNotification::because('Stored value is its own processor: it sends no notification.');
    }
}
