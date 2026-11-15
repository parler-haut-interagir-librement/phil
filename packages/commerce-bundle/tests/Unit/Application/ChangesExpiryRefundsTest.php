<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\Contract\DocumentSeries;
use Phil\CommerceBundle\Application\CoreDocumentTypes;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Application\OrderChanges;
use Phil\CommerceBundle\Application\OrderExpiry;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Application\OrderMarkingStore;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Change\OrderChangeState;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\Contract\ExpiringOrders;
use Phil\CommerceBundle\Domain\Event\OrderRefunded;
use Phil\CommerceBundle\Domain\Exception\OrderChangeRefused;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Channel;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Refund\RefundState;
use Phil\CommerceBundle\Domain\Repository\ChannelRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\FakeGateway;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryCommerceStore;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryReceipts;
use Phil\CommerceBundle\Tests\Unit\Support\PricedProducts;
use Phil\CommerceBundle\Tests\Unit\Support\RecordingDispatcher;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderChanges::class)]
#[CoversClass(OrderExpiry::class)]
#[CoversClass(Refunds::class)]
#[CoversClass(OrderRefunded::class)]
#[CoversClass(DocumentRegister::class)]
#[CoversClass(CoreDocumentTypes::class)]
final class ChangesExpiryRefundsTest extends TestCase
{
    private InMemoryCommerceStore $store;

    private InMemoryReceipts $receipts;

    private OrderModifier $modifier;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryCommerceStore();
        $this->receipts = new InMemoryReceipts();
        $this->modifier = new OrderModifier([new PricedProducts()], [], []);
    }

    public function testAPreparedChangeLeavesTheOrderAndPreviewsItsTotal(): void
    {
        $order = $this->placed();
        $changes = new OrderChanges($this->store, $this->modifier, $this->receipts);

        $change = $changes->prepare($order, $this->lines(['dice' => 3, 'mat' => 0, 'pen' => 2]), 'Phone call', 'clerk-1', $this->context(), new DateTimeImmutable());

        self::assertSame([$change], $this->store->changes);
        self::assertSame(4_000, $change->totalBefore->minorUnits);
        self::assertSame(3_500, $change->totalAfter->minorUnits);
        self::assertSame(-500, $change->difference()->minorUnits);
        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame(4_000, $order->total()->minorUnits);
        self::assertCount(2, $order->lines());
    }

    public function testAConfirmedChangeIsAppliedPlacedAgainAndVersioned(): void
    {
        $order = $this->placed();
        $journal = new InMemoryCommerceStore();
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), journal: $journal);
        $changes = new OrderChanges($this->store, new OrderModifier([new PricedProducts()], [], [], lifecycle: $lifecycle), $this->receipts, $lifecycle);
        $change = $changes->prepare($order, $this->lines(['dice' => 3, 'mat' => 0, 'pen' => 2]), 'Phone call', 'clerk-1', $this->context(), new DateTimeImmutable());
        $at = new DateTimeImmutable('2026-11-11T15:00:00Z');

        self::assertSame($change, $changes->confirm($change, $order, 'manager-1', $this->context(), $at));

        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame($at, $order->placedAt());
        self::assertSame(2, $order->revision());
        self::assertSame(3_500, $order->total()->minorUnits);
        self::assertSame(['product:dice 3', 'product:pen 2'], array_map(static fn (OrderLine $line): string => $line->reference->value().' '.$line->quantity, $order->lines()));
        self::assertSame(OrderChangeState::Confirmed, $change->state());
        self::assertSame(2, $change->revision());
        self::assertSame(-500, $change->difference()->minorUnits);
        self::assertSame(['reopen', 'place'], array_map(static fn (OrderStateChange $move): ?string => $move->transition, $journal->moves));
    }

    public function testAChangeWithoutTheWorkflowMovesTheOrderItself(): void
    {
        $order = $this->placed();
        $changes = new OrderChanges($this->store, $this->modifier, $this->receipts);
        $change = $changes->prepare($order, $this->lines(['dice' => 2]), 'More', 'clerk-1', $this->context(), new DateTimeImmutable());

        $changes->confirm($change, $order, 'manager-1', $this->context(), new DateTimeImmutable());

        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame(5_000, $order->total()->minorUnits);
    }

    public function testAChangePreparedOnAnOlderRevisionIsRefused(): void
    {
        $order = $this->placed();
        $changes = new OrderChanges($this->store, $this->modifier, $this->receipts);
        $first = $changes->prepare($order, $this->lines(['dice' => 2]), 'One', 'clerk-1', $this->context(), new DateTimeImmutable());
        $second = $changes->prepare($order, $this->lines(['dice' => 5]), 'Two', 'clerk-1', $this->context(), new DateTimeImmutable());
        $changes->confirm($first, $order, 'manager-1', $this->context(), new DateTimeImmutable());

        try {
            $changes->confirm($second, $order, 'manager-1', $this->context(), new DateTimeImmutable());
            self::fail('A stale change.');
        } catch (OrderChangeRefused $orderChangeRefused) {
            self::assertStringContainsString('was prepared on revision 1 of the order 2026-0001, which is at revision 2 now', $orderChangeRefused->getMessage());
        }

        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame(OrderChangeState::Prepared, $second->state());
    }

    public function testASettledChangeLeavesTheOrderAsItWas(): void
    {
        $order = $this->placed();
        $changes = new OrderChanges($this->store, $this->modifier, $this->receipts);
        $change = $changes->prepare($order, $this->lines(['dice' => 2]), 'One', 'clerk-1', $this->context(), new DateTimeImmutable());
        $changes->abandon($change, new DateTimeImmutable());
        self::assertSame(OrderChangeState::Abandoned, $change->state());

        try {
            $changes->confirm($change, $order, 'manager-1', $this->context(), new DateTimeImmutable());
            self::fail('An abandoned change.');
        } catch (OrderChangeRefused) {
        }

        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame(1, $order->revision());
    }

    public function testAChangeIsConfirmedOnItsOwnOrder(): void
    {
        $order = $this->placed();
        $changes = new OrderChanges($this->store, $this->modifier, $this->receipts);
        $change = $changes->prepare($order, $this->lines(['dice' => 2]), 'One', 'clerk-1', $this->context(), new DateTimeImmutable());

        $this->expectException(OrderChangeRefused::class);
        $this->expectExceptionMessageIsOrContains('is not a change of the order 2026-0002.');

        $changes->confirm($change, $this->placed('2026-0002'), 'manager-1', $this->context(), new DateTimeImmutable());
    }

    public function testOnlyAPlacedOrderWithoutAReceiptChanges(): void
    {
        $changes = new OrderChanges($this->store, $this->modifier, $this->receipts);
        $cart = Order::open('2026-0003', Currency::of('CAD'));

        try {
            $changes->prepare($cart, $this->lines(['dice' => 1]), 'R', 'clerk-1', $this->context(), new DateTimeImmutable());
            self::fail('A cart.');
        } catch (OrderChangeRefused $orderChangeRefused) {
            self::assertSame('The order 2026-0003 is not placed: only a placed order is changed this way.', $orderChangeRefused->getMessage());
        }

        $order = $this->placed();
        $this->receipts->add(Receipt::issue('R-1', $order, Issuer::of('Store', [], []), new DateTimeImmutable()));

        $this->expectException(OrderChangeRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 has its receipt');

        $changes->prepare($order, $this->lines(['dice' => 1]), 'R', 'clerk-1', $this->context(), new DateTimeImmutable());
    }

    public function testTheSweepExpiresByEachChannelThenByDefault(): void
    {
        $store = Channel::create('store', 'Store', Currency::of('CAD'), 'fr');
        $store->expireUnpaidOrders(48, 7);

        $kiosk = Channel::create('kiosk', 'Kiosk', Currency::of('CAD'), 'fr');
        $kiosk->expireUnpaidOrders(null, null);

        $orders = new RecordingExpiringOrders([$this->placed('A'), $this->placed('B')], ['OLD']);
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $journal = new InMemoryCommerceStore();
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), journal: $journal);

        $sweep = new OrderExpiry($orders, $this->channels($store, $kiosk), $lifecycle, 24, 30)->sweep($at, 50);

        self::assertSame([
            'unpaid store - 2026-11-09T10:00:00+00:00 50',
            'expired store - 2026-11-04T10:00:00+00:00 50',
            'unpaid * store,kiosk 2026-11-10T10:00:00+00:00 50',
            'expired * store,kiosk 2026-10-12T10:00:00+00:00 50',
        ], $orders->calls);
        self::assertSame(['A', 'B'], $sweep->expired);
        self::assertSame(['OLD', 'OLD'], $sweep->deleted);
        self::assertSame(['OLD', 'OLD'], $orders->deleted);
        self::assertSame(OrderState::Cancelled, $orders->orders[0]->state());
        self::assertSame($at, $orders->orders[0]->expiredAt());
        self::assertSame('expire', $journal->moves[0]->transition);
    }

    public function testTheSweepIsBoundedAndCanBeTurnedOff(): void
    {
        $orders = new RecordingExpiringOrders([], []);

        new OrderExpiry($orders, $this->channels(), null, null, null)->sweep(new DateTimeImmutable(), 0);
        self::assertSame([], $orders->calls);

        new OrderExpiry($orders, $this->channels(), null, 1, 1)->sweep(new DateTimeImmutable('2026-11-11T10:00:00Z'), 50_000);
        self::assertSame(['unpaid * - 2026-11-11T09:00:00+00:00 10000', 'expired * - 2026-11-10T10:00:00+00:00 10000'], $orders->calls);

        $orders->calls = [];
        new OrderExpiry($orders, $this->channels())->sweep(new DateTimeImmutable('2026-11-11T10:00:00Z'), -5);
        self::assertSame(['unpaid * - 2026-11-10T10:00:00+00:00 1', 'expired * - 2026-10-12T10:00:00+00:00 1'], $orders->calls);
    }

    public function testTheSweepExpiresWithoutTheWorkflowToo(): void
    {
        $orders = new RecordingExpiringOrders([$this->placed('A')], []);

        new OrderExpiry($orders, $this->channels())->sweep(new DateTimeImmutable());

        self::assertNotNull($orders->orders[0]->expiredAt());
    }

    public function testARefundIsGrantedThenExecutedByTheGateway(): void
    {
        [$payment, $gateway, $refunds] = $this->paid();
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $refund = $refunds->grant($payment, $this->cad(400), 'Broken die', 'clerk-1', $at);
        self::assertSame([$refund], $this->store->refunds);
        self::assertSame(RefundState::Granted, $refund->state());
        self::assertSame([], $gateway->refunds);
        self::assertFalse($refunds->isSingleStep());

        $refunds->execute($refund, 'manager-1', $at);
        self::assertSame(RefundState::Executed, $refund->state());
        self::assertSame([[$payment->id, 400]], $gateway->refunds);

        try {
            $refunds->execute($refund, 'manager-1', $at);
            self::fail('An executed refund is not executed again.');
        } catch (PaymentRefused) {
        }

        // The processor is not asked twice for the same money.
        self::assertSame([[$payment->id, 400]], $gateway->refunds);
    }

    public function testAnExecutedRefundIsAnnouncedWithTheIdentifiersOfTheOrder(): void
    {
        $payments = new InMemoryPayments();
        $gateway = new FakeGateway('card');
        $order = Order::open('2026-0042', Currency::of('CAD'));
        $this->modifier->add($order, SellableReference::of('product', 'dice'), 1, $this->context());
        $order->changeChannel('web');
        $order->assignCustomer('member-7');
        $order->place(new DateTimeImmutable());

        $orders = new InMemoryOrders();
        $orders->add($order);

        $service = new Payments([$gateway], $payments);
        [$payment] = $service->start($order, 'card', new DateTimeImmutable());
        $service->notify('card', 'evt_1|'.$payment->externalReference().'|captured', ['x-signature' => ['ok']]);
        $recorded = new RecordingDispatcher();
        $refunds = new Refunds($this->store, $payments, $service, false, $orders, $recorded);
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $refund = $refunds->grant($payment, $this->cad(100), 'Broken die', 'clerk-1', $at);
        self::assertSame([], $recorded->heard());
        $refunds->execute($refund, 'manager-1', $at);

        self::assertEquals([new OrderRefunded('2026-0042', $refund->id, $payment->id, 'web', 'member-7', $this->cad(100), $at)], $recorded->events);
        self::assertSame($at, $recorded->events[0] instanceof OrderRefunded ? $recorded->events[0]->occurredAt() : null);
    }

    public function testARefundOfAnOrderNotFoundIsAnnouncedWithoutChannelNorCustomer(): void
    {
        $payments = new InMemoryPayments();
        $gateway = new FakeGateway('card');
        $payment = $this->capture($payments, $gateway);
        $recorded = new RecordingDispatcher();
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $refund = new Refunds($this->store, $payments, new Payments([$gateway], $payments), true, new InMemoryOrders(), $recorded)
            ->grant($payment, $this->cad(100), 'Goodwill', 'owner', $at);

        self::assertEquals([new OrderRefunded('2026-0001', $refund->id, $payment->id, null, null, $this->cad(100), $at)], $recorded->events);
    }

    public function testARefundIsAnnouncedWithoutChannelNorCustomerWhenNoOrdersAreRead(): void
    {
        $payments = new InMemoryPayments();
        $gateway = new FakeGateway('card');
        $payment = $this->capture($payments, $gateway);
        $recorded = new RecordingDispatcher();
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $refund = new Refunds($this->store, $payments, new Payments([$gateway], $payments), true, dispatcher: $recorded)
            ->grant($payment, $this->cad(100), 'Goodwill', 'owner', $at);

        self::assertEquals([new OrderRefunded('2026-0001', $refund->id, $payment->id, null, null, $this->cad(100), $at)], $recorded->events);
    }

    public function testWhatIsGrantedNeverExceedsWhatIsLeft(): void
    {
        [$payment, , $refunds] = $this->paid();
        $first = $refunds->grant($payment, $this->cad(3_600), 'One', 'clerk-1', new DateTimeImmutable());

        try {
            $refunds->grant($payment, $this->cad(401), 'Two', 'clerk-1', new DateTimeImmutable());
            self::fail('More than is left.');
        } catch (PaymentRefused $paymentRefused) {
            self::assertSame('A refund of 4.01 CAD is refused: it must be above zero and at most 4.00 CAD.', $paymentRefused->getMessage());
        }

        $refunds->withdraw($first, new DateTimeImmutable());
        self::assertSame(RefundState::Withdrawn, $first->state());
        self::assertSame(4_000, $refunds->grant($payment, $this->cad(4_000), 'All', 'clerk-1', new DateTimeImmutable())->amount->minorUnits);

        $this->expectException(PaymentRefused::class);

        $refunds->grant($payment, $this->cad(0), 'Nothing', 'clerk-1', new DateTimeImmutable());
    }

    public function testASmallStoreGrantsAndExecutesAtOnce(): void
    {
        $payments = new InMemoryPayments();
        $gateway = new FakeGateway('card');
        $payment = $this->capture($payments, $gateway);
        $refunds = new Refunds($this->store, $payments, new Payments([$gateway], $payments), true);

        $refund = $refunds->grant($payment, $this->cad(100), 'Goodwill', 'owner', new DateTimeImmutable());

        self::assertTrue($refunds->isSingleStep());
        self::assertSame(RefundState::Executed, $refund->state());
        self::assertSame('owner', $refund->executedBy());
        self::assertSame([[$payment->id, 100]], $gateway->refunds);
    }

    public function testOnlyACompletedPaymentIsRefunded(): void
    {
        $payments = new InMemoryPayments();
        $order = $this->placed();
        [$payment] = new Payments([new FakeGateway('card')], $payments)->start($order, 'card', new DateTimeImmutable());
        $refunds = new Refunds($this->store, $payments, new Payments([], $payments));

        $this->expectException(PaymentRefused::class);

        $refunds->grant($payment, $this->cad(1), 'R', 'clerk-1', new DateTimeImmutable());
    }

    public function testARefundWithoutItsPaymentIsNotExecuted(): void
    {
        [$payment, , $refunds] = $this->paid();
        $refund = $refunds->grant($payment, $this->cad(1), 'R', 'clerk-1', new DateTimeImmutable());
        $orphans = new Refunds($this->store, new InMemoryPayments(), new Payments([], new InMemoryPayments()));

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('is without its payment');

        $orphans->execute($refund, 'manager-1', new DateTimeImmutable());
    }

    public function testTheRegisterNumbersEachTypeInItsSeries(): void
    {
        $series = new class implements DocumentSeries {
            /** @var array<string, int> */
            public array $last = [];

            #[Override]
            public function nextIn(DocumentType $type): string
            {
                $this->last[$type->code] = ($this->last[$type->code] ?? 0) + 1;

                return $type->format($this->last[$type->code]);
            }
        };
        $register = new DocumentRegister([new CoreDocumentTypes('R-', 'CN-', 6, ['packing_slip' => ['prefix' => 'PS-', 'digits' => 4]])], $series);

        self::assertSame(['receipt', 'credit_note', 'packing_slip'], array_keys($register->types()));
        self::assertSame($register->types(), $register->types());
        self::assertSame('PS-', $register->type('packing_slip')->prefix);
        self::assertSame('PS-0001', $register->next('packing_slip'));
        self::assertSame('PS-0002', $register->next('packing_slip'));
        self::assertSame('R-000001', $register->next('receipt'));

        try {
            $register->type('invoice');
            self::fail('An unknown type.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('"invoice" is not a document type of the register.', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The document register numbers through a DocumentSeries: none is wired.');

        new DocumentRegister([new CoreDocumentTypes()])->next('receipt');
    }

    /**
     * The receipts and credit notes are listed among the buyer's documents; a type of the application is kept for the
     * store, unless it says otherwise.
     */
    public function testEachTypeIsSeenAsFarAsItsVisibilitySays(): void
    {
        $register = new DocumentRegister([new CoreDocumentTypes('R-', 'CN-', 6, [
            'packing_slip' => ['prefix' => 'PS-', 'digits' => 4],
            'gift_card' => ['prefix' => 'GC-', 'digits' => 6, 'visibility' => 'unlisted'],
            'warranty' => ['prefix' => 'W-', 'digits' => 6, 'visibility' => 'listed'],
            'pick_list' => ['prefix' => 'PL-', 'digits' => 6, 'visibility' => 'hidden'],
        ])]);

        self::assertSame(['receipt', 'credit_note', 'warranty'], array_keys($register->listed()));
        self::assertSame(
            ['receipt' => Visibility::Listed, 'credit_note' => Visibility::Listed, 'packing_slip' => Visibility::Hidden, 'gift_card' => Visibility::Unlisted, 'warranty' => Visibility::Listed, 'pick_list' => Visibility::Hidden],
            array_map(static fn (DocumentType $type): Visibility => $type->visibility, $register->types()),
        );
    }

    public function testTwoTypesNeverShareACodeOrAPrefix(): void
    {
        try {
            new DocumentRegister([new CoreDocumentTypes(), new CoreDocumentTypes('X-', 'Y-')])->types();
            self::fail('A code declared twice.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('The document type "receipt" is declared twice.', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The document types "receipt" and "packing_slip" share the prefix "R-": their numbers would meet.');

        new DocumentRegister([new CoreDocumentTypes(declared: ['packing_slip' => ['prefix' => 'R-', 'digits' => 8]])])->types();
    }

    /**
     * @return array{Payment, FakeGateway, Refunds}
     */
    private function paid(): array
    {
        $payments = new InMemoryPayments();
        $gateway = new FakeGateway('card');
        $payment = $this->capture($payments, $gateway);

        return [$payment, $gateway, new Refunds($this->store, $payments, new Payments([$gateway], $payments))];
    }

    private function capture(InMemoryPayments $payments, FakeGateway $gateway): Payment
    {
        $service = new Payments([$gateway], $payments);
        [$payment] = $service->start($this->placed(), 'card', new DateTimeImmutable());
        $service->notify('card', 'evt_1|'.$payment->externalReference().'|captured', ['x-signature' => ['ok']]);
        self::assertSame(PaymentState::Completed, $payment->state());

        return $payment;
    }

    private function channels(Channel ...$channels): ChannelRepository
    {
        return new readonly class(array_values($channels)) implements ChannelRepository {
            /** @param list<Channel> $channels */
            public function __construct(private array $channels)
            {
            }

            #[Override]
            public function find(string $code): ?Channel
            {
                return null;
            }

            #[Override]
            public function all(): array
            {
                return $this->channels;
            }

            #[Override]
            public function add(Channel $channel): void
            {
            }
        };
    }

    /**
     * @param array<string, int> $quantities
     *
     * @return list<OrderChangeLine>
     */
    private function lines(array $quantities): array
    {
        $lines = [];

        foreach ($quantities as $id => $quantity) {
            $lines[] = OrderChangeLine::of(SellableReference::of('product', $id), $quantity);
        }

        return $lines;
    }

    private function placed(string $number = '2026-0001'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $this->modifier->add($order, SellableReference::of('product', 'dice'), 1, $this->context());
        $this->modifier->add($order, SellableReference::of('product', 'mat'), 1, $this->context());

        $order->place(new DateTimeImmutable());

        return $order;
    }

    private function context(): PricingContext
    {
        return PricingContext::of(1, new DateTimeImmutable());
    }

    private function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }

    public function testAChangeThatSetsNothingStillPreviewsWhatTheProcessorsNowCompute(): void
    {
        $order = $this->placed();
        $fee = new class implements OrderProcessor {
            #[Override]
            public function process(Order $order): void
            {
                $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Handling', Money::of(100, Currency::of('CAD')), origin: 'handling')]);
            }
        };
        $changes = new OrderChanges($this->store, new OrderModifier([new PricedProducts()], [], [$fee]), $this->receipts);

        // A line wished for none, which the order does not have: nothing to add, and the total is still computed afresh.
        $change = $changes->prepare($order, $this->lines(['pen' => 0]), 'Nothing', 'clerk-1', $this->context(), new DateTimeImmutable());

        self::assertSame(4_100, $change->totalAfter->minorUnits);
        self::assertSame(4_000, $order->total()->minorUnits);
    }

    public function testAnOrderThatGotItsReceiptSinceTheChangeWasPreparedIsNotChanged(): void
    {
        $order = $this->placed();
        $changes = new OrderChanges($this->store, $this->modifier, $this->receipts);
        $change = $changes->prepare($order, $this->lines(['dice' => 2]), 'One', 'clerk-1', $this->context(), new DateTimeImmutable());
        $this->receipts->add(Receipt::issue('R-1', $order, Issuer::of('Store', [], []), new DateTimeImmutable()));

        try {
            $changes->confirm($change, $order, 'manager-1', $this->context(), new DateTimeImmutable());
            self::fail('A receipt was issued.');
        } catch (OrderChangeRefused $orderChangeRefused) {
            self::assertSame('The order 2026-0001 has its receipt: a credit note and a new order replace it.', $orderChangeRefused->getMessage());
        }

        self::assertSame(OrderChangeState::Prepared, $change->state());
        self::assertSame(1, $order->revision());
    }

    public function testASweepWithoutALimitLooksAtAThousandOrders(): void
    {
        $orders = new RecordingExpiringOrders([], []);

        new OrderExpiry($orders, $this->channels())->sweep(new DateTimeImmutable('2026-11-11T10:00:00Z'));

        self::assertSame(['unpaid * - 2026-11-10T10:00:00+00:00 1000', 'expired * - 2026-10-12T10:00:00+00:00 1000'], $orders->calls);
    }

    public function testTheDocumentTypesOfTheCoreAreNumberedOnEightDigitsByDefault(): void
    {
        $types = iterator_to_array(new CoreDocumentTypes()->documentTypes(), false);

        self::assertSame([8, 8], array_map(static fn (DocumentType $type): int => $type->digits, $types));
    }
}

/**
 * Records what the sweep asks, and hands it the same orders each time.
 */
final class RecordingExpiringOrders implements ExpiringOrders
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<string> */
    public array $deleted = [];

    /**
     * @param list<Order>  $orders
     * @param list<string> $expired
     */
    public function __construct(
        public array $orders,
        private readonly array $expired,
    ) {
    }

    #[Override]
    public function unpaidPlacedBefore(?array $channels, array $except, DateTimeImmutable $before, int $limit): array
    {
        $this->calls[] = $this->call('unpaid', $channels, $except, $before, $limit);

        return array_values(array_filter($this->orders, static fn (Order $order): bool => OrderState::Placed === $order->state()));
    }

    #[Override]
    public function expiredBefore(?array $channels, array $except, DateTimeImmutable $before, int $limit): array
    {
        $this->calls[] = $this->call('expired', $channels, $except, $before, $limit);

        return $this->expired;
    }

    #[Override]
    public function deleteExpired(string $orderNumber): void
    {
        $this->deleted[] = $orderNumber;
    }

    /**
     * @param list<string>|null $channels
     * @param list<string>      $except
     */
    private function call(string $what, ?array $channels, array $except, DateTimeImmutable $before, int $limit): string
    {
        return \sprintf('%s %s %s %s %d', $what, null === $channels ? '*' : implode(',', $channels), [] === $except ? '-' : implode(',', $except), $before->format(\DATE_ATOM), $limit);
    }
}
