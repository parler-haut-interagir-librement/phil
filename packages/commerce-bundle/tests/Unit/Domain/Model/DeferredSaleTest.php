<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
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
use Phil\CommerceBundle\Domain\Model\DeferredSale;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeferredSale::class)]
#[CoversClass(DeferredSaleEvent::class)]
#[CoversClass(DeferredSaleRefused::class)]
#[CoversClass(DeferredSaleState::class)]
final class DeferredSaleTest extends TestCase
{
    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-11 10:00:00');
    }

    public function testALayawayHoldsTheStockFromTheOrderAndLetsTheGoodsGoOncePaid(): void
    {
        [$sale, $events] = DeferredSale::open($this->placed(), DeferredSalePolicy::layaway(Deposit::share(2_000), 60), $this->at);

        self::assertSame([[DeferredSaleOpened::class, '1'], [StockCommitted::class, '1']], $this->described($events));
        self::assertSame($this->at, $events[0]->occurredAt());
        self::assertSame([DeferredSaleState::AwaitingDeposit, true, false, 200], [$sale->state(), $sale->isStockCommitted(), $sale->mayFulfil(), $sale->deposit->minorUnits]);
        self::assertSame('2027-01-10 10:00:00', $sale->dueAt?->format('Y-m-d H:i:s'));
        self::assertSame([BalanceTrigger::Date, StockCommitment::OnOrder, Fulfilment::OnBalance, $this->at, 1], [$sale->trigger, $sale->commitment, $sale->fulfilment, $sale->openedAt, $sale->version()]);
        self::assertSame(200, $sale->amountDue($this->cad(1_000), $this->cad(0))->minorUnits);
        self::assertSame(50, $sale->amountDue($this->cad(1_000), $this->cad(150))->minorUnits);

        self::assertSame([], $sale->recordPaid($this->cad(199), $this->cad(1_000), $this->at));
        self::assertSame(DeferredSaleState::AwaitingDeposit, $sale->state());

        $later = $this->at->modify('+1 day');
        $received = $sale->recordPaid($this->cad(200), $this->cad(1_000), $later);
        self::assertSame([[DepositReceived::class, '1']], $this->described($received));
        self::assertSame([DeferredSaleState::AwaitingBalance, $later, false], [$sale->state(), $sale->depositPaidAt(), $sale->mayFulfil()]);
        self::assertSame(0, $sale->amountDue($this->cad(1_000), $this->cad(200))->minorUnits);
        self::assertSame([], $sale->recordPaid($this->cad(300), $this->cad(1_000), $later));

        $due = $this->at->modify('+60 days');
        $called = $sale->callBalance(BalanceTrigger::Date, $due);
        self::assertSame([[BalanceCalled::class, '1']], $this->described($called));
        self::assertSame([DeferredSaleState::BalanceDue, $due], [$sale->state(), $sale->balanceCalledAt()]);
        self::assertSame(700, $sale->amountDue($this->cad(1_000), $this->cad(300))->minorUnits);

        $settled = $sale->recordPaid($this->cad(1_000), $this->cad(1_000), $due);
        self::assertSame([[DeferredSaleSettled::class, '1']], $this->described($settled));
        self::assertSame([DeferredSaleState::Settled, $due, true, true], [$sale->state(), $sale->closedAt(), $sale->mayFulfil(), $sale->isStockCommitted()]);
        self::assertSame(0, $sale->amountDue($this->cad(1_000), $this->cad(0))->minorUnits);
        self::assertSame([], $sale->recordPaid($this->cad(1_000), $this->cad(1_000), $due));
    }

    public function testInstallmentsLetTheGoodsGoOnTheDeposit(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::installments(Deposit::share(5_000), 30), $this->at);

        $sale->recordPaid($this->cad(500), $this->cad(1_000), $this->at);

        self::assertTrue($sale->mayFulfil());
        $sale->cancel($this->at);
        self::assertFalse($sale->mayFulfil());
    }

    public function testAPreorderCommitsTheStockOncePaid(): void
    {
        [$sale, $opened] = DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::none()), $this->at);

        self::assertSame([[DeferredSaleOpened::class, '1']], $this->described($opened));
        self::assertNull($sale->dueAt);
        self::assertSame([[DepositReceived::class, '1']], $this->described($sale->recordPaid($this->cad(0), $this->cad(1_000), $this->at)));
        self::assertTrue($sale->waitsFor(BalanceTrigger::Availability));
        self::assertFalse($sale->waitsFor(BalanceTrigger::Shipment));

        $sale->callBalance(BalanceTrigger::Availability, $this->at);
        self::assertFalse($sale->waitsFor(BalanceTrigger::Availability));

        self::assertSame([[DeferredSaleSettled::class, '1'], [StockCommitted::class, '1']], $this->described($sale->recordPaid($this->cad(1_000), $this->cad(1_000), $this->at)));
        self::assertTrue($sale->isStockCommitted());
    }

    public function testPaidInFullAtOnceTheSaleSettlesWithItsDeposit(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::share(2_000)), $this->at);

        self::assertSame(
            [[DepositReceived::class, '1'], [DeferredSaleSettled::class, '1'], [StockCommitted::class, '1']],
            $this->described($sale->recordPaid($this->cad(1_200), $this->cad(1_000), $this->at)),
        );
        self::assertSame([$this->at, $this->at], [$sale->depositPaidAt(), $sale->closedAt()]);
    }

    public function testATotalAmendedBelowTheDepositSettlesTheSale(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::fixed($this->cad(800))), $this->at);

        self::assertSame(
            [[DepositReceived::class, '1'], [DeferredSaleSettled::class, '1'], [StockCommitted::class, '1']],
            $this->described($sale->recordPaid($this->cad(500), $this->cad(500), $this->at)),
        );
    }

    public function testACancelledSaleReleasesTheStockItHeld(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::layaway(Deposit::none(), 10), $this->at);

        self::assertSame([[DeferredSaleCancelled::class, '1'], [StockReleased::class, '1']], $this->described($sale->cancel($this->at)));
        self::assertSame([DeferredSaleState::Cancelled, false, $this->at], [$sale->state(), $sale->isStockCommitted(), $sale->closedAt()]);
        self::assertSame([], $sale->recordPaid($this->cad(1_000), $this->cad(1_000), $this->at));

        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The deferred sale of 1 is cancelled: it cannot be cancelled.');

        $sale->cancel($this->at);
    }

    public function testACancelledSaleWithoutStockReleasesNothing(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::none()), $this->at);

        self::assertSame([[DeferredSaleCancelled::class, '1']], $this->described($sale->cancel($this->at)));
    }

    public function testASettledSaleIsNotCancelled(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::none()), $this->at);
        $sale->recordPaid($this->cad(1_000), $this->cad(1_000), $this->at);

        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The deferred sale of 1 is settled: it cannot be cancelled.');

        $sale->cancel($this->at);
    }

    public function testTheBalanceIsNotCalledBeforeTheDeposit(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::share(1_000)), $this->at);

        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The deferred sale of 1 is awaiting deposit: it cannot call its balance.');

        $sale->callBalance(BalanceTrigger::Availability, $this->at);
    }

    public function testTheBalanceIsCalledByItsOwnTrigger(): void
    {
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::none()), $this->at);
        $sale->recordPaid($this->cad(0), $this->cad(1_000), $this->at);

        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The balance of 1 is called by availability, not by manual.');

        $sale->callBalance(BalanceTrigger::Manual, $this->at);
    }

    public function testADateCallsTheBalanceOnlyOnceDue(): void
    {
        $dueAt = $this->at->modify('+10 days');
        [$sale] = DeferredSale::open($this->placed(), DeferredSalePolicy::installments(Deposit::none(), 30), $this->at, $dueAt);
        $sale->recordPaid($this->cad(0), $this->cad(1_000), $this->at);
        self::assertSame($dueAt, $sale->dueAt);

        try {
            $sale->callBalance(BalanceTrigger::Date, $dueAt->modify('-1 second'));
            self::fail('A balance was called before its due date.');
        } catch (DeferredSaleRefused $deferredSaleRefused) {
            self::assertSame('The balance of 1 is not due before 2026-11-21T10:00:00+00:00.', $deferredSaleRefused->getMessage());
        }

        $sale->callBalance(BalanceTrigger::Date, $dueAt);
        self::assertSame(DeferredSaleState::BalanceDue, $sale->state());
    }

    public function testOnlyAPlacedOrderIsDeferred(): void
    {
        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 1 is not placed: only a placed order is paid later.');

        DeferredSale::open($this->cart(1_000), DeferredSalePolicy::preorder(Deposit::none()), $this->at);
    }

    public function testAnOrderWithNothingToPayIsNotDeferred(): void
    {
        $order = $this->cart(0);
        $order->place($this->at);

        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 1 has nothing to pay: nothing is deferred.');

        DeferredSale::open($order, DeferredSalePolicy::preorder(Deposit::none()), $this->at);
    }

    public function testADateTriggerNeedsADueDate(): void
    {
        $policy = new DeferredSalePolicy(Deposit::none(), BalanceTrigger::Date, StockCommitment::OnBalance, Fulfilment::OnBalance);

        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The balance of 1 is called by a date, and none is given or counted.');

        DeferredSale::open($this->placed(), $policy, $this->at);
    }

    public function testADueDateIsGivenForADateTriggerOnly(): void
    {
        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The balance of 1 is called by shipment: it has no due date.');

        DeferredSale::open($this->placed(), DeferredSalePolicy::preorder(Deposit::none(), BalanceTrigger::Shipment), $this->at, $this->at->modify('+1 day'));
    }

    public function testTheDueDateIsAfterTheOpening(): void
    {
        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The balance of 1 is due after the sale opens.');

        DeferredSale::open($this->placed(), DeferredSalePolicy::layaway(Deposit::none(), 10), $this->at, $this->at);
    }

    public function testTheRefusalsNameTheOrder(): void
    {
        self::assertSame('No deferred sale is known for the order 7.', DeferredSaleRefused::unknown('7')->getMessage());
        self::assertSame('The order 7 is already paid later: an order has one deferred sale.', DeferredSaleRefused::alreadyDeferred('7')->getMessage());
    }

    /**
     * @param list<DeferredSaleEvent> $events
     *
     * @return list<array{class-string, string}>
     */
    private function described(array $events): array
    {
        return array_map(static fn (DeferredSaleEvent $event): array => [$event::class, $event->orderNumber], $events);
    }

    private function placed(): Order
    {
        $order = $this->cart(1_000);
        $order->place($this->at);

        return $order;
    }

    private function cart(int $price): Order
    {
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', $this->cad($price), 'standard'), 1, $this->cad($price));

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
