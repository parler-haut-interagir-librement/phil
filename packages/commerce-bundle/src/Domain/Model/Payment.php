<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * One payment of an order, through one method, named by a time-ordered UUID: an amount the server computed from the order, and the state its
 * processor's notifications move. An order may have several, for a split payment.
 *
 * **The state moves only by a notification its gateway has verified**, never by the buyer's return to the store,
 * and **each notification applies once**: its id is kept, so a replay changes nothing. A partial refund keeps
 * the payment completed; the last one makes it refunded.
 *
 * An **offline payment** — a cheque, a bank transfer, cash handed over later — is declared without a gateway: it
 * waits as processing until someone of the store confirms the money came in, or cancels it; its refunds are recorded
 * by hand too, since no processor sends them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_payment')]
#[ORM\Index(columns: ['order_number'])]
#[ORM\UniqueConstraint(columns: ['method', 'external_reference'])]
final class Payment
{
    public Money $amount {
        get => Money::of($this->amountMinorUnits, Currency::of($this->currencyCode));
    }

    public Money $refunded {
        get => Money::of($this->refundedMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'amount', type: 'bigint')]
    private int $amountMinorUnits;

    #[ORM\Column(name: 'refunded', type: 'bigint')]
    private int $refundedMinorUnits = 0;

    #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
    private string $currencyCode;

    #[ORM\Column(name: 'state', length: 16, enumType: PaymentState::class)]
    private PaymentState $state = PaymentState::New;

    #[ORM\Column(name: 'external_reference', length: 255, nullable: true)]
    private ?string $externalReference = null;

    /** @var list<string> the ids of the notifications already applied */
    #[ORM\Column(name: 'applied_notifications', type: 'json')]
    private array $appliedNotifications = [];

    #[ORM\Column(name: 'offline', type: 'boolean', options: ['default' => false])]
    private bool $offline = false;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'method', length: 32)]
        public readonly string $method,
        Money $amount,
        #[ORM\Column(name: 'created_at')]
        public readonly DateTimeImmutable $createdAt,
    ) {
        $this->amountMinorUnits = $amount->minorUnits;
        $this->currencyCode = $amount->currency->code;
    }

    /**
     * A new payment of an amount of a placed order; `Payments::start()` checks the order and the amount.
     */
    public static function create(Order $order, string $method, Money $amount, DateTimeImmutable $at): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method)) {
            throw InvalidValue::because(\sprintf('"%s" is not a payment method: up to 32 lowercase letters, digits or "_", starting with a letter.', $method));
        }

        if (!$amount->currency->equals($order->currency)) {
            throw CurrencyMismatch::between($order->currency, $amount->currency);
        }

        return new self(Uuid::v7()->toRfc4122(), $order->number, $method, $amount, $at);
    }

    /**
     * A payment declared offline, for an amount of a placed order: it waits as processing, named `offline:<id>`,
     * until the money comes in. `Payments::declareOffline()` checks the order, the method and the amount.
     */
    public static function declareOffline(Order $order, string $method, Money $amount, DateTimeImmutable $at): self
    {
        $payment = self::create($order, $method, $amount, $at);
        $payment->offline = true;
        $payment->externalReference = 'offline:'.$payment->id;
        $payment->state = PaymentState::Processing;

        return $payment;
    }

    public function isOffline(): bool
    {
        return $this->offline;
    }

    /**
     * Someone of the store confirms that the money of an offline payment came in.
     */
    public function confirmReceived(): void
    {
        $this->checkOffline(PaymentState::Processing, 'confirmed');
        $this->state = PaymentState::Completed;
    }

    /**
     * Gives up an offline payment whose money never came: it no longer holds its share of the order.
     */
    public function cancelOffline(): void
    {
        $this->checkOffline(PaymentState::Processing, 'cancelled');
        $this->state = PaymentState::Cancelled;
    }

    /**
     * Records money handed back for an offline payment, as no processor will say so.
     */
    public function recordOfflineRefund(Money $amount): void
    {
        $this->checkOffline(PaymentState::Completed, 'refunded');

        if (!$amount->currency->equals($this->amount->currency)) {
            throw PaymentRefused::currency($this->id, $this->amount->currency->code, $amount->currency->code);
        }

        $refundable = $this->refundable();

        if (!$amount->isPositive() || $amount->isGreaterThan($refundable)) {
            throw PaymentRefused::amount('A refund', $amount->toDecimal().' '.$amount->currency->code, $refundable->toDecimal().' '.$refundable->currency->code);
        }

        $this->refundedMinorUnits += $amount->minorUnits;

        if ($amount->equals($refundable)) {
            $this->state = PaymentState::Refunded;
        }
    }

    public function state(): PaymentState
    {
        return $this->state;
    }

    public function externalReference(): ?string
    {
        return $this->externalReference;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * What is left to refund: the amount captured less what went back.
     */
    public function refundable(): Money
    {
        return $this->amount->subtract($this->refunded);
    }

    /**
     * Whether the payment still holds, or may still hold, money towards its order.
     */
    public function counts(): bool
    {
        return \in_array($this->state, [PaymentState::New, PaymentState::Processing, PaymentState::Authorized, PaymentState::Completed, PaymentState::Disputed], true);
    }

    /**
     * Whether the money was ever captured, whatever happened to it since.
     */
    public function wasCaptured(): bool
    {
        return \in_array($this->state, [PaymentState::Completed, PaymentState::Refunded, PaymentState::Disputed, PaymentState::ChargedBack], true);
    }

    /**
     * The processor took the payment: its reference names the payment in every notification to come.
     */
    public function handOver(PaymentStart $start): void
    {
        if (PaymentState::New !== $this->state) {
            throw PaymentRefused::alreadyStarted($this->id);
        }

        $this->externalReference = $start->externalReference;
        $this->state = PaymentState::Processing;
    }

    /**
     * Gives the payment up before its processor settled it, so that it no longer holds its share of the order: the
     * buyer left, or no notification came in time. A notification that comes later still applies.
     */
    public function abandon(): void
    {
        if (!\in_array($this->state, [PaymentState::New, PaymentState::Processing], true)) {
            throw PaymentRefused::notAbandonable($this->id, $this->state);
        }

        $this->state = PaymentState::Abandoned;
    }

    /**
     * Whether a notification of this id was applied: a gateway that is its own processor applies a refund under its
     * idempotency key, so that the key tells whether the refund was made.
     */
    public function hasApplied(string $notificationId): bool
    {
        return \in_array($notificationId, $this->appliedNotifications, true);
    }

    /**
     * Applies a verified notification of the processor; `false` when it was already applied.
     */
    public function apply(PaymentNotification $notification): bool
    {
        if ($this->hasApplied($notification->id)) {
            return false;
        }

        $this->state = match ($notification->event) {
            PaymentEvent::Processing => $this->from([PaymentState::New], PaymentState::Processing, $notification),
            PaymentEvent::Authorized => $this->from([PaymentState::New, PaymentState::Processing, PaymentState::Abandoned], PaymentState::Authorized, $notification),
            PaymentEvent::Captured => $this->from([PaymentState::New, PaymentState::Processing, PaymentState::Authorized, PaymentState::Abandoned], PaymentState::Completed, $notification),
            PaymentEvent::Failed => $this->from([PaymentState::New, PaymentState::Processing, PaymentState::Authorized, PaymentState::Abandoned], PaymentState::Failed, $notification),
            PaymentEvent::Cancelled => $this->from([PaymentState::New, PaymentState::Processing, PaymentState::Authorized, PaymentState::Abandoned], PaymentState::Cancelled, $notification),
            PaymentEvent::Refunded => $this->refund($notification),
            PaymentEvent::DisputeOpened => $this->from([PaymentState::Completed], PaymentState::Disputed, $notification),
            PaymentEvent::DisputeWon => $this->from([PaymentState::Disputed], PaymentState::Completed, $notification),
            PaymentEvent::DisputeLost => $this->from([PaymentState::Disputed], PaymentState::ChargedBack, $notification),
            // An ignored notification names no payment: whatever state the payment is in, it does not apply.
            PaymentEvent::Ignored => $this->from([], $this->state, $notification),
        };

        $this->appliedNotifications[] = $notification->id;

        return true;
    }

    private function checkOffline(PaymentState $from, string $what): void
    {
        if (!$this->offline || $from !== $this->state) {
            throw PaymentRefused::offline($this->id, $this->state, $what);
        }
    }

    /**
     * @param list<PaymentState> $from
     */
    private function from(array $from, PaymentState $to, PaymentNotification $notification): PaymentState
    {
        if (!\in_array($this->state, $from, true)) {
            throw PaymentRefused::event($this->id, $this->state, $notification->event);
        }

        return $to;
    }

    private function refund(PaymentNotification $notification): PaymentState
    {
        $this->from([PaymentState::Completed], PaymentState::Completed, $notification);
        // A refund notification always carries its amount; zero would be refused below like any amount it cannot be.
        $amount = $notification->amount ?? Money::zero($this->amount->currency);

        if (!$amount->currency->equals($this->amount->currency)) {
            throw PaymentRefused::currency($this->id, $this->amount->currency->code, $amount->currency->code);
        }

        $refundable = $this->refundable();

        if (!$amount->isPositive() || $amount->isGreaterThan($refundable)) {
            throw PaymentRefused::amount('A refund', $amount->toDecimal().' '.$amount->currency->code, $refundable->toDecimal().' '.$refundable->currency->code);
        }

        $this->refundedMinorUnits += $amount->minorUnits;

        return $amount->equals($refundable) ? PaymentState::Refunded : PaymentState::Completed;
    }
}
