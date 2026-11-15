<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Deferred\DeferredSaleState;
use Phil\CommerceBundle\Domain\Deferred\Fulfilment;
use Phil\CommerceBundle\Domain\Deferred\StockCommitment;
use Phil\CommerceBundle\Domain\Event\BalanceCalled;
use Phil\CommerceBundle\Domain\Event\DeferredSaleCancelled;
use Phil\CommerceBundle\Domain\Event\DeferredSaleEvent;
use Phil\CommerceBundle\Domain\Event\DeferredSaleOpened;
use Phil\CommerceBundle\Domain\Event\DeferredSaleSettled;
use Phil\CommerceBundle\Domain\Event\DepositReceived;
use Phil\CommerceBundle\Domain\Event\StockCommitted;
use Phil\CommerceBundle\Domain\Event\StockReleased;
use Phil\CommerceBundle\Domain\Exception\DeferredSaleRefused;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A placed order paid later, under a deferred sale policy copied when it opens: a deposit, then a balance called by
 * its trigger, with the stock committed on the order or on the balance. **An order has at most one**, keyed by its
 * number, so one deferred due date.
 *
 * The sale follows what the order's payments hold, which `DeferredSales` hands it: it never takes a payment itself.
 * Each change returns the events it gives, in the order they happened.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_deferred_sale')]
#[ORM\Index(columns: ['state', 'due_at'])]
final class DeferredSale
{
    public Money $deposit {
        get => Money::of($this->depositMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'state', length: 16, enumType: DeferredSaleState::class)]
    private DeferredSaleState $state = DeferredSaleState::AwaitingDeposit;

    #[ORM\Column(name: 'stock_committed')]
    private bool $stockCommitted = false;

    #[ORM\Column(name: 'deposit_paid_at', nullable: true)]
    private ?DateTimeImmutable $depositPaidAt = null;

    #[ORM\Column(name: 'balance_called_at', nullable: true)]
    private ?DateTimeImmutable $balanceCalledAt = null;

    #[ORM\Column(name: 'closed_at', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'balance_trigger', length: 16, enumType: BalanceTrigger::class)]
        public readonly BalanceTrigger $trigger,
        #[ORM\Column(name: 'stock_commitment', length: 16, enumType: StockCommitment::class)]
        public readonly StockCommitment $commitment,
        #[ORM\Column(name: 'fulfilment', length: 16, enumType: Fulfilment::class)]
        public readonly Fulfilment $fulfilment,
        #[ORM\Column(name: 'deposit', type: 'bigint')]
        private readonly int $depositMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'opened_at')]
        public readonly DateTimeImmutable $openedAt,
        #[ORM\Column(name: 'due_at', nullable: true)]
        public readonly ?DateTimeImmutable $dueAt,
    ) {
    }

    /**
     * Defers the payment of a placed order. The deposit is the policy's share of the order's total, kept as it is
     * then; with the `Date` trigger, the due date is `$dueAt`, or the policy's days counted from `$at`. When the
     * policy commits the stock on the order, it is committed now.
     *
     * @return array{self, list<DeferredSaleEvent>}
     *
     * @throws DeferredSaleRefused
     */
    public static function open(Order $order, DeferredSalePolicy $policy, DateTimeImmutable $at, ?DateTimeImmutable $dueAt = null): array
    {
        if (OrderState::Placed !== $order->state()) {
            throw DeferredSaleRefused::orderNotPlaced($order->number);
        }

        $total = $order->total();

        if (!$total->isPositive()) {
            throw DeferredSaleRefused::nothingToPay($order->number);
        }

        $sale = new self(
            $order->number,
            $policy->trigger,
            $policy->commitment,
            $policy->fulfilment,
            $policy->deposit->of($total)->minorUnits,
            $total->currency->code,
            $at,
            self::dueDate($order->number, $policy, $at, $dueAt),
        );
        $events = [new DeferredSaleOpened($order->number, $at)];

        if (StockCommitment::OnOrder === $policy->commitment) {
            $events[] = $sale->commitStock($at);
        }

        return [$sale, $events];
    }

    public function state(): DeferredSaleState
    {
        return $this->state;
    }

    public function isStockCommitted(): bool
    {
        return $this->stockCommitted;
    }

    public function depositPaidAt(): ?DateTimeImmutable
    {
        return $this->depositPaidAt;
    }

    public function balanceCalledAt(): ?DateTimeImmutable
    {
        return $this->balanceCalledAt;
    }

    /**
     * When the sale was settled or cancelled; `null` while it is open.
     */
    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Whether the sale waits for this trigger to call its balance: its deposit is paid and its balance is not called.
     */
    public function waitsFor(BalanceTrigger $trigger): bool
    {
        return DeferredSaleState::AwaitingBalance === $this->state && $trigger === $this->trigger;
    }

    /**
     * Whether the goods may leave: once the deposit is paid, or once settled, as the policy says; never once cancelled.
     */
    public function mayFulfil(): bool
    {
        return match ($this->fulfilment) {
            Fulfilment::OnDeposit => DeferredSaleState::Cancelled !== $this->state && $this->depositPaidAt instanceof DateTimeImmutable,
            Fulfilment::OnBalance => DeferredSaleState::Settled === $this->state,
        };
    }

    /**
     * What is to pay now: the rest of the deposit while it is awaited, the rest of the total once the balance is called,
     * and nothing in between or once closed. Paying more than asked is never refused: the sale settles early.
     */
    public function amountDue(Money $total, Money $paid): Money
    {
        $owed = match ($this->state) {
            DeferredSaleState::AwaitingDeposit => $this->deposit->subtract($paid),
            DeferredSaleState::BalanceDue => $total->subtract($paid),
            default => Money::zero($total->currency),
        };

        return $owed->isPositive() ? $owed : Money::zero($owed->currency);
    }

    /**
     * Follows what the order's payments hold: the deposit is received once they reach it, and the sale is settled once
     * they reach the order's total — committing the stock then, if it was not on the order.
     *
     * @phpstan-impure
     *
     * @return list<DeferredSaleEvent>
     */
    public function recordPaid(Money $paid, Money $total, DateTimeImmutable $at): array
    {
        if ($this->state->isClosed()) {
            return [];
        }

        $events = [];

        if (!$this->depositPaidAt instanceof DateTimeImmutable && ($paid->compare($this->deposit) >= 0 || $paid->compare($total) >= 0)) {
            $this->depositPaidAt = $at;
            $this->state = DeferredSaleState::AwaitingBalance;
            $events[] = new DepositReceived($this->orderNumber, $at);
        }

        if ($paid->compare($total) >= 0) {
            $this->state = DeferredSaleState::Settled;
            $this->closedAt = $at;
            $events[] = new DeferredSaleSettled($this->orderNumber, $at);

            if (!$this->stockCommitted) {
                $events[] = $this->commitStock($at);
            }
        }

        return $events;
    }

    /**
     * The trigger came: the balance is to pay now. A date calls it only once the due date is reached.
     *
     * @phpstan-impure
     *
     * @return list<DeferredSaleEvent>
     *
     * @throws DeferredSaleRefused
     */
    public function callBalance(BalanceTrigger $trigger, DateTimeImmutable $at): array
    {
        if (DeferredSaleState::AwaitingBalance !== $this->state) {
            throw DeferredSaleRefused::state($this->orderNumber, $this->state, 'call its balance');
        }

        if ($trigger !== $this->trigger) {
            throw DeferredSaleRefused::otherTrigger($this->orderNumber, $this->trigger, $trigger);
        }

        if ($this->dueAt instanceof DateTimeImmutable && $at < $this->dueAt) {
            throw DeferredSaleRefused::notDue($this->orderNumber, $this->dueAt);
        }

        $this->state = DeferredSaleState::BalanceDue;
        $this->balanceCalledAt = $at;

        return [new BalanceCalled($this->orderNumber, $at)];
    }

    /**
     * Gives the sale up before it is paid in full, releasing the stock it held. What was paid, and the order itself,
     * are the application's to refund and cancel.
     *
     * @phpstan-impure
     *
     * @return list<DeferredSaleEvent>
     *
     * @throws DeferredSaleRefused
     */
    public function cancel(DateTimeImmutable $at): array
    {
        if ($this->state->isClosed()) {
            throw DeferredSaleRefused::state($this->orderNumber, $this->state, 'be cancelled');
        }

        $this->state = DeferredSaleState::Cancelled;
        $this->closedAt = $at;
        $events = [new DeferredSaleCancelled($this->orderNumber, $at)];

        if ($this->stockCommitted) {
            $this->stockCommitted = false;
            $events[] = new StockReleased($this->orderNumber, $at);
        }

        return $events;
    }

    private function commitStock(DateTimeImmutable $at): StockCommitted
    {
        $this->stockCommitted = true;

        return new StockCommitted($this->orderNumber, $at);
    }

    private static function dueDate(string $orderNumber, DeferredSalePolicy $policy, DateTimeImmutable $at, ?DateTimeImmutable $dueAt): ?DateTimeImmutable
    {
        if (BalanceTrigger::Date !== $policy->trigger) {
            if ($dueAt instanceof DateTimeImmutable) {
                throw DeferredSaleRefused::dueDateWithoutDateTrigger($orderNumber, $policy->trigger);
            }

            return null;
        }

        if (!$dueAt instanceof DateTimeImmutable && null !== $policy->dueAfterDays) {
            $dueAt = $at->modify(\sprintf('+%d days', $policy->dueAfterDays));
        }

        if (!$dueAt instanceof DateTimeImmutable) {
            throw DeferredSaleRefused::noDueDate($orderNumber);
        }

        if ($dueAt <= $at) {
            throw DeferredSaleRefused::dueBeforeOpening($orderNumber);
        }

        return $dueAt;
    }
}
