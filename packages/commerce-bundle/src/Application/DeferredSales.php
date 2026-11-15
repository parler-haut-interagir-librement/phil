<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Contract\StockCommitments;
use Phil\CommerceBundle\Domain\Contract\DeferredSaleRepository;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Event\DeferredSaleEvent;
use Phil\CommerceBundle\Domain\Event\StockCommitted;
use Phil\CommerceBundle\Domain\Event\StockReleased;
use Phil\CommerceBundle\Domain\Exception\DeferredSaleRefused;
use Phil\CommerceBundle\Domain\Model\DeferredSale;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Sales paid later — preorders, installments, layaways —, each a placed order under a deferred sale policy: opened,
 * followed as its payments come, its balance called by its trigger, settled or cancelled.
 *
 * Each change hands its events, in the order they happened, first to the `StockCommitments` ports — a committed
 * stock is held, a released one freed — then to the event dispatcher, by their classes. The application flushes the
 * unit of work, as it does for orders and payments.
 */
final readonly class DeferredSales
{
    /** The most sales `callDue()` calls at once. */
    public const int MAX_BATCH = 1_000;

    /**
     * @param iterable<StockCommitments> $commitments
     */
    public function __construct(
        private DeferredSaleRepository $sales,
        private Payments $payments,
        private iterable $commitments = [],
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * Defers the payment of a placed order, then reads what its payments already hold: a deposit paid before the sale
     * opens counts.
     *
     * @throws DeferredSaleRefused when the order is not placed, has nothing to pay, is already deferred, or has no due
     *                             date its trigger needs
     */
    public function open(Order $order, DeferredSalePolicy $policy, DateTimeImmutable $at, ?DateTimeImmutable $dueAt = null): DeferredSale
    {
        if ($this->sales->findDeferredSale($order->number) instanceof DeferredSale) {
            throw DeferredSaleRefused::alreadyDeferred($order->number);
        }

        [$sale, $events] = DeferredSale::open($order, $policy, $at, $dueAt);
        $this->sales->addDeferredSale($sale);
        $this->publish($events);
        $this->follow($sale, $order, $at);

        return $sale;
    }

    /**
     * Reads the order's payments again — after a notification, a refund — and moves its deferred sale on: deposit
     * received, settled. An order paid at once has no deferred sale, and `null` is returned.
     */
    public function recordPayments(Order $order, DateTimeImmutable $at): ?DeferredSale
    {
        $sale = $this->sales->findDeferredSale($order->number);

        if ($sale instanceof DeferredSale) {
            $this->follow($sale, $order, $at);
        }

        return $sale;
    }

    /**
     * A trigger came for an order — it shipped, its goods arrived, someone of the shop called it —: calls the balance
     * of its deferred sale, if it waits for this trigger. Whoever sees the trigger calls this for every order it
     * concerns, deferred or not.
     *
     * @return bool whether a balance was called
     *
     * @throws DeferredSaleRefused when a date is given as the trigger before the due date
     */
    public function callBalance(string $orderNumber, BalanceTrigger $trigger, DateTimeImmutable $at): bool
    {
        $sale = $this->sales->findDeferredSale($orderNumber);

        if (!$sale instanceof DeferredSale || !$sale->waitsFor($trigger)) {
            return false;
        }

        $this->publish($sale->callBalance($trigger, $at));

        return true;
    }

    /**
     * Calls the balances whose due date is reached, the earliest first, at most `$limit` of them: a scheduled task
     * calls this until it returns fewer than its limit.
     *
     * @return list<string> the numbers of the orders whose balance was called
     */
    public function callDue(DateTimeImmutable $at, int $limit = 100): array
    {
        if ($limit < 1 || $limit > self::MAX_BATCH) {
            throw InvalidValue::because(\sprintf('The balances are called 1 to %d at a time, not %d.', self::MAX_BATCH, $limit));
        }

        $called = [];

        foreach ($this->sales->dueForBalance($at, $limit) as $orderNumber) {
            if ($this->callBalance($orderNumber, BalanceTrigger::Date, $at)) {
                $called[] = $orderNumber;
            }
        }

        return $called;
    }

    /**
     * Gives the deferred sale of an order up, releasing the stock it held. What was paid, and the order itself, are the
     * application's to refund and cancel, through `Payments` and `Order::cancel()`.
     *
     * @throws DeferredSaleRefused when the order has no deferred sale, or it is already closed
     */
    public function cancel(string $orderNumber, DateTimeImmutable $at): DeferredSale
    {
        $sale = $this->get($orderNumber);
        $this->publish($sale->cancel($at));

        return $sale;
    }

    public function find(string $orderNumber): ?DeferredSale
    {
        return $this->sales->findDeferredSale($orderNumber);
    }

    /**
     * Whether the goods of an order may leave: always for an order paid at once, and as its policy says for a deferred
     * one.
     */
    public function mayFulfil(string $orderNumber): bool
    {
        return $this->sales->findDeferredSale($orderNumber)?->mayFulfil() ?? true;
    }

    /**
     * What the buyer is asked now: the rest of the deposit, the rest of the balance once called, or nothing; for an
     * order paid at once, what its payments may still ask.
     */
    public function amountDue(Order $order): Money
    {
        $summary = $this->payments->summary($order);
        $sale = $this->sales->findDeferredSale($order->number);

        return $sale instanceof DeferredSale ? $sale->amountDue($order->total(), $summary->net()) : $summary->payable();
    }

    private function follow(DeferredSale $sale, Order $order, DateTimeImmutable $at): void
    {
        $this->publish($sale->recordPaid($this->payments->summary($order)->net(), $order->total(), $at));
    }

    private function get(string $orderNumber): DeferredSale
    {
        return $this->sales->findDeferredSale($orderNumber) ?? throw DeferredSaleRefused::unknown($orderNumber);
    }

    /**
     * @param list<DeferredSaleEvent> $events
     */
    private function publish(array $events): void
    {
        foreach ($events as $event) {
            foreach ($this->commitments as $commitments) {
                match (true) {
                    $event instanceof StockCommitted => $commitments->commit($event->orderNumber),
                    $event instanceof StockReleased => $commitments->release($event->orderNumber),
                    default => null,
                };
            }
        }

        foreach ($events as $event) {
            $this->dispatcher?->dispatch($event);
        }
    }
}
