<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\Contract\ReturnLabels;
use Phil\CommerceReturnsBundle\Application\Contract\ShipmentDates;
use Phil\CommerceReturnsBundle\Application\Returns;
use Phil\CommerceReturnsBundle\Application\ReturnsGateway;
use Phil\CommerceReturnsBundle\Domain\Event\ReturnSettled;
use Phil\CommerceReturnsBundle\Domain\Event\ReturnStateChanged;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\DeliveryRefund;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\Resolution;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnPolicy;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\Events;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryReturns;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * The steps beside the straight way of a return: the window from the shipment, the rate limit, questions to the
 * customer, expiry, labels, anonymization, and the events of each step.
 */
#[CoversClass(Returns::class)]
#[CoversClass(ReturnPolicy::class)]
final class ReturnStepsTest extends TestCase
{
    private InMemoryOrders $orders;

    private InMemoryReturns $returns;

    private Events $events;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrders();
        $this->returns = new InMemoryReturns();
        $this->events = new Events();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testTheWindowCountsFromTheLastShipmentWhenThePolicySays(): void
    {
        $order = $this->order();
        $shipped = new class implements ShipmentDates {
            #[Override]
            public function lastShippedAt(string $orderNumber): ?DateTimeImmutable
            {
                return 'A-1' === $orderNumber ? new DateTimeImmutable('2026-11-13 10:00:00') : null;
            }
        };
        $late = $this->at->modify('+35 days');

        self::assertSame(ReturnState::Requested, $this->service(ReturnPolicy::of(30, true), $shipped)->request($order, [$this->ask()], $late)->state());
        self::assertSame(ReturnState::Requested, $this->service(ReturnPolicy::of(30, true), $shipped)->request($order, [$this->ask()], new DateTimeImmutable('2026-12-13 10:00:00'))->state());

        foreach ([[ReturnPolicy::of(30, false), $shipped, $late], [ReturnPolicy::of(30, true), null, $late], [ReturnPolicy::of(30, true), $shipped, new DateTimeImmutable('2026-12-13 10:00:01')]] as [$policy, $dates, $at]) {
            try {
                $this->service($policy, $dates)->request($order, [$this->ask()], $at);
                self::fail('A late return was accepted.');
            } catch (ReturnRefused $returnRefused) {
                self::assertSame('The order A-1 is past its 30 days of returns.', $returnRefused->getMessage());
            }
        }

        self::assertTrue(ReturnPolicy::of(0)->isOpen($this->at, $this->at));
        self::assertFalse(ReturnPolicy::of(0)->isOpen($this->at, $this->at->modify('+1 second')));
    }

    public function testACustomerAsksForReturnsWithinTheRateLimit(): void
    {
        $order = $this->order();
        $limiter = new RateLimiterFactory(['id' => 'returns', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'], new InMemoryStorage());
        $service = $this->service(limiter: $limiter);

        $service->request($order, [$this->ask(1)], $this->at, 'ada');
        $service->request($order, [$this->ask(1)], $this->at, 'grace');
        $service->request($order, [$this->ask(1)], $this->at);
        $service->request($order, [$this->ask(1)], $this->at);
        $service->request($order, [$this->ask(1)], $this->at);

        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('Too many returns were asked for in a short time: try again later.');

        $service->request($order, [$this->ask(1)], $this->at, 'ada');
    }

    public function testWithoutARateLimiterNobodyIsLimited(): void
    {
        $order = $this->order();
        $service = $this->service();

        self::assertSame(ReturnState::Requested, $service->request($order, [$this->ask(1)], $this->at, 'ada')->state());
        self::assertSame(ReturnState::Requested, $service->request($order, [$this->ask(1)], $this->at, 'ada')->state());
    }

    public function testTheShopAsksTheCustomerForInformation(): void
    {
        $service = $this->service();
        $return = $service->request($this->order(), [$this->ask()], $this->at);

        $asked = $service->askForInformation($return->id, 'A photo of the box, please.', $this->at->modify('+1 hour'));
        self::assertSame([ReturnState::AwaitingInformation, 'A photo of the box, please.'], [$asked->state(), $asked->note()]);
        self::assertSame(1, $return->quantityOf('product:TEA'));

        self::assertSame(ReturnState::Requested, $service->provideInformation($return->id, $this->at->modify('+2 hours'))->state());
        $service->authorize($return->id, $this->at);
        self::assertSame(ReturnState::AwaitingInformation, $service->askForInformation($return->id, 'Which serial number?', $this->at)->state());
        self::assertSame(ReturnState::Rejected, $service->reject($return->id, 'No answer', $this->at)->state());

        $states = array_map(static fn (ReturnStateChanged $event): array => [$event->from, $event->to], array_values(array_filter($this->events->dispatched, static fn (object $event): bool => $event instanceof ReturnStateChanged)));
        self::assertSame([
            [null, ReturnState::Requested],
            [ReturnState::Requested, ReturnState::AwaitingInformation],
            [ReturnState::AwaitingInformation, ReturnState::Requested],
            [ReturnState::Requested, ReturnState::Authorized],
            [ReturnState::Authorized, ReturnState::AwaitingInformation],
            [ReturnState::AwaitingInformation, ReturnState::Rejected],
        ], $states);

        $service->anonymize($return->id);
        self::assertNull($return->note());
    }

    public function testAQuestionSaysWhatTheShopNeeds(): void
    {
        $service = $this->service();
        $return = $service->request($this->order(), [$this->ask()], $this->at);

        try {
            $service->askForInformation($return->id, ' ', $this->at);
            self::fail('An empty question was asked.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('A question to the customer says what the shop needs, in 1 to 255 characters.', $invalidValue->getMessage());
            self::assertSame(ReturnState::Requested, $return->state());
        }

        $service->cancel($service->askForInformation($return->id, 'Why?', $this->at)->id, $this->at);
        self::assertSame(ReturnState::Cancelled, $return->state());
    }

    /**
     * Each step and the settlement are told, identifiers and amounts only.
     */
    public function testEachStepIsTold(): void
    {
        $service = $this->service();
        $return = $service->request($this->order(), [$this->ask()], $this->at);
        $service->authorize($return->id, $this->at);
        $service->receive($return->id, ['product:TEA' => ItemCondition::Resellable], $this->at);
        $service->refund($return->id, $this->at->modify('+1 day'));

        $settled = $this->events->dispatched[4];
        self::assertInstanceOf(ReturnSettled::class, $settled);
        self::assertSame([$return->id, 'A-1', Resolution::Refund, 500], [$settled->returnId, $settled->orderNumber, $settled->resolution, $settled->value->minorUnits]);
        self::assertEquals($this->at->modify('+1 day'), $settled->occurredAt());
        $resolved = $this->events->dispatched[3];
        self::assertInstanceOf(ReturnStateChanged::class, $resolved);
        self::assertSame([$return->id, 'A-1', ReturnState::Received, ReturnState::Resolved], [$resolved->returnId, $resolved->orderNumber, $resolved->from, $resolved->to]);
        $requested = $this->events->dispatched[0];
        self::assertInstanceOf(ReturnStateChanged::class, $requested);
        self::assertSame($this->at, $requested->occurredAt());
        self::assertCount(5, $this->events->dispatched);
    }

    public function testAnAuthorizedReturnWhoseGoodsNeverCameExpires(): void
    {
        $order = $this->order();
        $old = $this->service(ReturnPolicy::of(expiryDays: 14))->request($order, [$this->ask(1)], $this->at);
        $recent = $this->service(ReturnPolicy::of(expiryDays: 14))->request($order, [$this->ask(1)], $this->at);
        $waiting = $this->service(ReturnPolicy::of(expiryDays: 14))->request($order, [$this->ask(1)], $this->at);
        $older = $this->service(ReturnPolicy::of(expiryDays: 14))->request($order, [$this->ask(1)], $this->at);
        $this->service()->authorize($old->id, $this->at);
        $this->service()->authorize($older->id, $this->at);
        $this->service()->authorize($recent->id, $this->at->modify('+1 day'));
        $now = $this->at->modify('+14 days +1 second');

        self::assertSame([], $this->service()->expire($now), 'A policy without expiry lets returns wait.');
        $before = \count($this->events->dispatched);
        self::assertSame([$old, $older], $this->service(ReturnPolicy::of(expiryDays: 14))->expire($now));
        self::assertSame([ReturnState::Expired, ReturnState::Authorized, ReturnState::Requested], [$old->state(), $recent->state(), $waiting->state()]);
        $changes = array_map(static fn (object $event): array => $event instanceof ReturnStateChanged ? [$event->returnId, $event->from, $event->to, $event->occurredAt()] : [], \array_slice($this->events->dispatched, $before));
        self::assertSame([[$old->id, ReturnState::Authorized, ReturnState::Expired, $now], [$older->id, ReturnState::Authorized, ReturnState::Expired, $now]], $changes);
        self::assertSame($now, $old->changedAt());
        self::assertSame(1, $this->service()->request($order, [$this->ask(1)], $this->at)->quantityOf('product:TEA'), 'The expired return freed its unit.');
        $expired = array_last($this->events->dispatched);
        self::assertInstanceOf(ReturnStateChanged::class, $expired);
    }

    public function testAnAuthorizedReturnGetsItsLabel(): void
    {
        $labels = new class implements ReturnLabels {
            /** @var list<string> */
            public array $asked = [];

            #[Override]
            public function labelFor(ReturnRequest $return, DateTimeImmutable $at): ReturnLabel
            {
                $this->asked[] = $return->id;

                return ReturnLabel::of('carrier', 'TRACK-1', 'labels/1.pdf');
            }
        };
        $service = $this->service(labels: $labels);
        $return = $service->request($this->order(), [$this->ask()], $this->at);

        try {
            $service->label($return->id, $this->at);
            self::fail('A requested return got a label.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame(\sprintf('The return %s is requested: only an authorized return gets a label or a slip.', $return->id), $returnRefused->getMessage());
            self::assertSame([], $labels->asked);
        }

        $service->authorize($return->id, $this->at);
        $label = $service->label($return->id, $this->at);

        self::assertSame(['carrier', 'TRACK-1', 'labels/1.pdf'], [$label->carrier, $label->tracking, $label->document]);
        self::assertEquals($label, $return->label());

        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('Return labels need a carrier adapter: implement ReturnLabels.');

        $this->service()->label($return->id, $this->at);
    }

    public function testTheDefaultPolicyGivesTheDeliveryBackWhenTheShopIsAtFault(): void
    {
        $order = $this->order(Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(1_000, Currency::of('CAD'))));
        $return = new Returns($this->orders, $this->returns, new Payments([new ReturnsGateway()], new InMemoryPayments()))->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Defective]], $this->at);

        self::assertSame([1_000, 1_500, 0], [$return->deliveryRefund()->minorUnits, $return->value()->minorUnits, $return->restockingFee]);
        self::assertSame(DeliveryRefund::ShopsFault, ReturnPolicy::of()->deliveryRefund);
    }

    private function service(?ReturnPolicy $policy = null, ?ShipmentDates $shipments = null, ?RateLimiterFactory $limiter = null, ?ReturnLabels $labels = null): Returns
    {
        return new Returns($this->orders, $this->returns, new Payments([new ReturnsGateway()], new InMemoryPayments()), $policy, shipments: $shipments, labels: $labels, limiter: $limiter, dispatcher: $this->events);
    }

    /**
     * @return array{line: string, quantity: int, reason: ReturnReason}
     */
    private function ask(int $quantity = 1): array
    {
        return ['line' => 'product:TEA', 'quantity' => $quantity, 'reason' => ReturnReason::ChangedMind];
    }

    /**
     * Tea: 5 at 5.00, placed on 2026-11-03.
     */
    private function order(?Adjustment $delivery = null): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'TEA'), 'Tea', Money::of(500, $cad), 'standard'), 5, Money::of(500, $cad));

        if ($delivery instanceof Adjustment) {
            $order->replaceAdjustments(AdjustmentType::Shipping, [$delivery]);
        }

        $order->place($this->at);
        $this->orders->add($order);

        return $order;
    }
}
