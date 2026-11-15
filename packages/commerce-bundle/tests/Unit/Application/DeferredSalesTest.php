<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\DeferredSales;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Deferred\DeferredSaleState;
use Phil\CommerceBundle\Domain\Event\BalanceCalled;
use Phil\CommerceBundle\Domain\Event\DeferredSaleCancelled;
use Phil\CommerceBundle\Domain\Event\DeferredSaleEvent;
use Phil\CommerceBundle\Domain\Event\DeferredSaleOpened;
use Phil\CommerceBundle\Domain\Event\DeferredSaleSettled;
use Phil\CommerceBundle\Domain\Event\DepositReceived;
use Phil\CommerceBundle\Domain\Event\StockCommitted;
use Phil\CommerceBundle\Domain\Event\StockReleased;
use Phil\CommerceBundle\Domain\Exception\DeferredSaleRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\FakeGateway;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryDeferredSales;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceBundle\Tests\Unit\Support\RecordingStockCommitments;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(DeferredSales::class)]
final class DeferredSalesTest extends TestCase
{
    private DateTimeImmutable $at;

    private InMemoryDeferredSales $stored;

    private Payments $payments;

    private RecordingStockCommitments $stock;

    /** @var list<string> what the ports and the listeners saw, in order */
    private array $seen = [];

    private int $notifications = 0;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-11 10:00:00');
        $this->stored = new InMemoryDeferredSales();
        $this->payments = new Payments([new FakeGateway()], new InMemoryPayments());
        $this->stock = new RecordingStockCommitments();
    }

    public function testALayawayFromTheOrderToTheBalance(): void
    {
        $sales = $this->sales();
        $order = $this->placed('1');

        $sale = $sales->open($order, DeferredSalePolicy::layaway(Deposit::share(2_000), 30), $this->at);

        self::assertSame($sale, $sales->find('1'));
        self::assertSame(['commit 1'], $this->stock->calls);
        self::assertSame(['DeferredSaleOpened 1', 'StockCommitted 1'], $this->seen);
        self::assertSame(200, $sales->amountDue($order)->minorUnits);
        self::assertFalse($sales->mayFulfil('1'));

        $this->pay($order, 200);
        self::assertSame($sale, $sales->recordPayments($order, $this->at));
        self::assertSame(DeferredSaleState::AwaitingBalance, $sale->state());
        self::assertSame(0, $sales->amountDue($order)->minorUnits);

        self::assertSame([], $sales->callDue($this->at->modify('+29 days')));
        self::assertSame(['1'], $sales->callDue($this->at->modify('+30 days')));
        self::assertSame(800, $sales->amountDue($order)->minorUnits);

        $this->pay($order, 800);
        $sales->recordPayments($order, $this->at);

        self::assertSame(DeferredSaleState::Settled, $sale->state());
        self::assertTrue($sales->mayFulfil('1'));
        self::assertSame(['commit 1'], $this->stock->calls);
        self::assertSame(['DeferredSaleOpened 1', 'StockCommitted 1', 'DepositReceived 1', 'BalanceCalled 1', 'DeferredSaleSettled 1'], $this->seen);
    }

    public function testADepositPaidBeforeTheSaleOpensCounts(): void
    {
        $order = $this->placed('1');
        $this->pay($order, 500);

        $sale = $this->sales()->open($order, DeferredSalePolicy::preorder(Deposit::share(5_000)), $this->at);

        self::assertSame(DeferredSaleState::AwaitingBalance, $sale->state());
        self::assertSame(['DeferredSaleOpened 1', 'DepositReceived 1'], $this->seen);
    }

    public function testAnOrderHasOneDeferredSale(): void
    {
        $sales = $this->sales();
        $order = $this->placed('1');
        $sales->open($order, DeferredSalePolicy::preorder(Deposit::none()), $this->at);

        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 1 is already paid later: an order has one deferred sale.');

        $sales->open($order, DeferredSalePolicy::preorder(Deposit::none()), $this->at);
    }

    public function testATriggerCallsOnlyTheSalesWaitingForIt(): void
    {
        $sales = $this->sales();
        $sales->open($this->placed('1'), DeferredSalePolicy::preorder(Deposit::none(), BalanceTrigger::Shipment), $this->at);
        $sales->open($this->placed('2'), DeferredSalePolicy::preorder(Deposit::share(1_000), BalanceTrigger::Shipment), $this->at);

        self::assertFalse($sales->callBalance('1', BalanceTrigger::Availability, $this->at));
        self::assertFalse($sales->callBalance('2', BalanceTrigger::Shipment, $this->at));
        self::assertFalse($sales->callBalance('3', BalanceTrigger::Shipment, $this->at));
        self::assertTrue($sales->callBalance('1', BalanceTrigger::Shipment, $this->at));
        self::assertFalse($sales->callBalance('1', BalanceTrigger::Shipment, $this->at));
        self::assertSame(DeferredSaleState::BalanceDue, $sales->find('1')?->state());
    }

    public function testTheDueBalancesAreCalledEarliestFirstWithinTheLimit(): void
    {
        $sales = $this->sales();
        $sales->open($this->placed('late'), DeferredSalePolicy::installments(Deposit::none(), 20), $this->at);
        $sales->open($this->placed('early'), DeferredSalePolicy::installments(Deposit::none(), 10), $this->at);
        $sales->open($this->placed('unpaid'), DeferredSalePolicy::installments(Deposit::share(1_000), 5), $this->at);

        self::assertSame(['early'], $sales->callDue($this->at->modify('+30 days'), 1));
        self::assertSame(['late'], $sales->callDue($this->at->modify('+30 days')));
        self::assertSame([], $sales->callDue($this->at->modify('+30 days'), DeferredSales::MAX_BATCH));
    }

    public function testTheDueBalancesAreCalledAHundredAtATimeByDefault(): void
    {
        $sales = $this->sales();

        foreach (range(1, 101) as $number) {
            $sales->open($this->placed((string) $number), DeferredSalePolicy::installments(Deposit::none(), 10), $this->at);
        }

        self::assertSame(array_map(strval(...), range(1, 100)), $sales->callDue($this->at->modify('+10 days')));
        self::assertSame(['101'], $sales->callDue($this->at->modify('+11 days')));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function limits(): iterable
    {
        yield 'none' => [0];
        yield 'beyond the most' => [DeferredSales::MAX_BATCH + 1];
    }

    #[DataProvider('limits')]
    public function testTheDueBalancesAreCalledInBoundedBatches(int $limit): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The balances are called 1 to 1000 at a time, not %d.', $limit));

        $this->sales()->callDue($this->at, $limit);
    }

    public function testACancelledSaleReleasesItsStockBeforeTheListenersHearIt(): void
    {
        $sales = $this->sales();
        $sales->open($this->placed('1'), DeferredSalePolicy::layaway(Deposit::none(), 10), $this->at);

        $this->seen = [];

        $sale = $sales->cancel('1', $this->at);

        self::assertSame(DeferredSaleState::Cancelled, $sale->state());
        self::assertSame(['commit 1', 'release 1'], $this->stock->calls);
        self::assertSame(['DeferredSaleCancelled 1', 'StockReleased 1'], $this->seen);
    }

    public function testAnUnknownSaleIsNotCancelled(): void
    {
        $this->expectException(DeferredSaleRefused::class);
        $this->expectExceptionMessageIsOrContains('No deferred sale is known for the order 9.');

        $this->sales()->cancel('9', $this->at);
    }

    public function testAnOrderPaidAtOnceIsFollowedByItsPayments(): void
    {
        $sales = new DeferredSales($this->stored, $this->payments);
        $order = $this->placed('1');

        self::assertNull($sales->recordPayments($order, $this->at));
        self::assertTrue($sales->mayFulfil('1'));
        self::assertSame(1_000, $sales->amountDue($order)->minorUnits);
        self::assertFalse($sales->callBalance('1', BalanceTrigger::Manual, $this->at));
    }

    public function testWithoutPortsOrDispatcherTheSaleStillMoves(): void
    {
        $sales = new DeferredSales($this->stored, $this->payments);

        $sale = $sales->open($this->placed('1'), DeferredSalePolicy::layaway(Deposit::none(), 10), $this->at);

        self::assertTrue($sale->isStockCommitted());
        self::assertSame(DeferredSaleState::AwaitingBalance, $sale->state());
    }

    private function sales(): DeferredSales
    {
        $dispatcher = new EventDispatcher();
        $stock = $this->stock;

        foreach ([DeferredSaleOpened::class, DepositReceived::class, BalanceCalled::class, DeferredSaleSettled::class, DeferredSaleCancelled::class, StockCommitted::class, StockReleased::class] as $class) {
            $dispatcher->addListener($class, function (DeferredSaleEvent $event) use ($stock): void {
                // Each stock event reaches the listeners once the ports ran.
                if ($event instanceof StockCommitted || $event instanceof StockReleased) {
                    self::assertContains(($event instanceof StockCommitted ? 'commit ' : 'release ').$event->orderNumber, $stock->calls);
                }

                $this->seen[] = new ReflectionClass($event)->getShortName().' '.$event->orderNumber;
            });
        }

        return new DeferredSales($this->stored, $this->payments, [$this->stock], $dispatcher);
    }

    private function pay(Order $order, int $minorUnits): void
    {
        [$payment] = $this->payments->start($order, 'card', $this->at, Money::of($minorUnits, Currency::of('CAD')));
        $payment->apply(PaymentNotification::of('n'.++$this->notifications, (string) $payment->externalReference(), PaymentEvent::Captured));
    }

    private function placed(string $number): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', Money::of(1_000, Currency::of('CAD')), 'standard'), 1, Money::of(1_000, Currency::of('CAD')));
        $order->place($this->at);

        return $order;
    }
}
