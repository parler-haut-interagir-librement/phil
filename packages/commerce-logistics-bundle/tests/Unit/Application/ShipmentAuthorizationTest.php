<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\AnyShipmentAuthorization;
use Phil\CommerceLogisticsBundle\Application\DenyShipmentForcing;
use Phil\CommerceLogisticsBundle\Application\PaidShipmentAuthorization;
use Phil\CommerceLogisticsBundle\Application\Shipments;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Event\ShipmentForced;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\RecordingDispatcher;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\SwitchableAuthorization;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An order leaves once its payments cover it, or once another authorization says so; forcing it takes a permission,
 * and the shipment keeps who forced it and why.
 */
#[CoversClass(Shipments::class)]
#[CoversClass(PaidShipmentAuthorization::class)]
#[CoversClass(AnyShipmentAuthorization::class)]
#[CoversClass(DenyShipmentForcing::class)]
#[CoversClass(ShipmentForced::class)]
#[CoversClass(Shipment::class)]
#[CoversClass(StockRefused::class)]
final class ShipmentAuthorizationTest extends TestCase
{
    private InMemoryLogistics $repository;

    private InMemoryPayments $paymentStore;

    private Stock $stock;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));

        $this->paymentStore = new InMemoryPayments();
        $this->stock = new Stock($this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-24T10:00:00Z');
    }

    /**
     * @return iterable<string, array{list<array{int, ?int}>, bool}>
     */
    public static function coverings(): iterable
    {
        // [captured, refunded] for each payment of an order of 20.00.
        yield 'unpaid' => [[], false];
        yield 'a deposit only' => [[[500, null]], false];
        yield 'a payment not captured' => [[[2_000, -1]], false];
        yield 'paid' => [[[2_000, null]], true];
        yield 'paid in two parts' => [[[1_500, null], [500, null]], true];
        yield 'paid more than due' => [[[2_500, null]], true];
        yield 'paid then partly refunded' => [[[2_000, 1]], false];
        yield 'paid more, refunded down to the total' => [[[2_500, 500]], true];
    }

    /**
     * @param list<array{int, ?int}> $payments captured minor units and refunded minor units, `-1` for never captured
     */
    #[DataProvider('coverings')]
    public function testAnOrderLeavesOnlyOnceItsPaymentsCoverIt(array $payments, bool $leaves): void
    {
        $order = $this->order(2_000);

        foreach ($payments as [$amount, $refunded]) {
            $this->pay($order, $amount, $refunded);
        }

        $shipments = $this->shipments(new PaidShipmentAuthorization($this->payments()));
        self::assertSame($leaves, $shipments->isAuthorized($order));

        if (!$leaves) {
            try {
                $shipments->ship($order, $this->at);
                self::fail('An order not covered left.');
            } catch (StockRefused $refused) {
                self::assertSame('The order 1 is not authorized to ship: its payments do not cover what it is due.', $refused->getMessage());
            }

            self::assertSame([], $this->repository->shipments);
            self::assertSame(['product:DICE' => 2], $shipments->left($order));

            return;
        }

        $shipment = $shipments->ship($order, $this->at);
        self::assertFalse($shipment->wasForced());
        self::assertSame([null, null], [$shipment->forcedBy, $shipment->forceReason]);
        self::assertSame([$shipment], $this->repository->shipments);
    }

    public function testAnOrderOfNothingIsCovered(): void
    {
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'GIFT'), 'GIFT', Money::zero(Currency::of('CAD')), 'goods'), 1, Money::zero(Currency::of('CAD')));
        $order->place($this->at);

        self::assertTrue(new PaidShipmentAuthorization($this->payments())->authorizes($order));
    }

    public function testAnOrderNotPlacedIsNeverAuthorized(): void
    {
        $authorization = new SwitchableAuthorization();
        $shipments = $this->shipments($authorization);
        $cart = Order::open('9', Currency::of('CAD'));

        self::assertFalse($shipments->isAuthorized($cart));
        self::assertSame([], $authorization->asked, 'a cart is not even asked about');
    }

    public function testOneAuthorizationThatSaysYesIsEnough(): void
    {
        $order = $this->order(2_000);
        $no = new SwitchableAuthorization(false);
        $yes = new SwitchableAuthorization();
        $after = new SwitchableAuthorization();

        self::assertTrue(new AnyShipmentAuthorization([$no, $yes, $after])->authorizes($order));
        self::assertSame([['1'], ['1'], []], [$no->asked, $yes->asked, $after->asked], 'the first yes ends the search');
        self::assertFalse(new AnyShipmentAuthorization([$no])->authorizes($order));
        self::assertFalse(new AnyShipmentAuthorization([])->authorizes($order), 'no authorization keeps every order');
    }

    public function testNobodyForcesAShipmentByDefault(): void
    {
        $order = $this->order(2_000);
        $shipments = new Shipments($this->stock, $this->repository, new SwitchableAuthorization(false));

        self::assertFalse(new DenyShipmentForcing()->allows($order, 'manager-2'));

        try {
            $shipments->force($order, 'manager-2', 'The buyer pays at the door', $this->at);
            self::fail('A shipment was forced without a permission.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('The shipment of the order 1 cannot be forced by this person.', $stockRefused->getMessage());
        }

        self::assertSame([], $this->repository->shipments);
    }

    public function testAForcedShipmentIsKeptWithWhoAndWhyThenAnnounced(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $order = $this->order(2_000);
        $this->stock->reserve($order, $this->at);
        $forcing = new SwitchableAuthorization();
        $dispatcher = new RecordingDispatcher();
        $shipments = new Shipments($this->stock, $this->repository, new SwitchableAuthorization(false), $forcing, $dispatcher);

        $shipment = $shipments->force($order, 'manager-2', 'The buyer pays at the door', $this->at, ['product:DICE' => 1], 'Canada Post', 'CP1');

        self::assertSame(['1/manager-2'], $forcing->asked);
        self::assertTrue($shipment->wasForced());
        self::assertSame(['manager-2', 'The buyer pays at the door', ['product:DICE' => 1], 'Canada Post', 'CP1'], [$shipment->forcedBy, $shipment->forceReason, $shipment->lines, $shipment->carrier, $shipment->tracking]);
        self::assertSame([$shipment], $this->repository->shipments);
        self::assertSame(4, $this->repository->level('DICE', 'warehouse')?->onHand(), 'the units left the shelf');

        $forced = array_values(array_filter($dispatcher->events, static fn (object $event): bool => $event instanceof ShipmentForced));
        self::assertCount(1, $forced);
        self::assertSame([$shipment->id, '1', 'manager-2', '2026-11-24T10:00:00+00:00'], [$forced[0]->shipmentId, $forced[0]->orderNumber, $forced[0]->actor, $forced[0]->occurredAt()->format(\DATE_ATOM)]);
    }

    public function testAForcedShipmentIsKeptWithoutADispatcher(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $order = $this->order(2_000);
        $this->stock->reserve($order, $this->at);
        $shipments = new Shipments($this->stock, $this->repository, new SwitchableAuthorization(false), new SwitchableAuthorization());

        self::assertTrue($shipments->force($order, 'manager-2', 'Reason', $this->at)->wasForced());
    }

    public function testTheMomentOfAForcedShipmentIsAnnouncedInUtc(): void
    {
        $event = new ShipmentForced('s', '1', 'manager-2', new DateTimeImmutable('2026-11-24T05:00:00-05:00'));

        self::assertSame(['2026-11-24T10:00:00+00:00', 'UTC'], [$event->occurredAt()->format(\DATE_ATOM), $event->occurredAt()->getTimezone()->getName()]);
    }

    public function testAForcedShipmentStillShipsOnlyWhatIsLeftOfAPlacedOrder(): void
    {
        $forcing = new SwitchableAuthorization();
        $shipments = new Shipments($this->stock, $this->repository, new SwitchableAuthorization(false), $forcing);
        $order = $this->order(2_000);

        foreach ([
            [static fn (): Shipment => $shipments->force(Order::open('9', Currency::of('CAD')), 'manager-2', 'Reason', new DateTimeImmutable()), 'The order 9 is not placed: nothing of it ships.'],
            [fn (): Shipment => $shipments->force($order, 'manager-2', 'Reason', $this->at, ['product:DICE' => 3]), '3 of product:DICE cannot ship: 2 are left to ship.'],
            [fn (): Shipment => $shipments->force($order, 'manager-2', 'Reason', $this->at, ['product:CUP' => 1]), 'The order 1 has no line product:CUP.'],
        ] as [$force, $message]) {
            try {
                $force();
                self::fail($message);
            } catch (StockRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        self::assertSame(['1/manager-2', '1/manager-2'], $forcing->asked, 'the order not placed is refused before anyone is asked');
        self::assertSame([], $this->repository->shipments);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badForcings(): iterable
    {
        yield 'no reason' => ['manager-2', '', 'A forced shipment has a reason: 1 to 255 characters on one line.'];
        yield 'a blank reason' => ['manager-2', '   ', 'A forced shipment has a reason: 1 to 255 characters on one line.'];
        yield 'a reason on two lines' => ['manager-2', "Paid\nat the door", 'A forced shipment has a reason: 1 to 255 characters on one line.'];
        yield 'a reason ending on a line break' => ['manager-2', "Paid\n", 'A forced shipment has a reason: 1 to 255 characters on one line.'];
        yield 'a reason that is not UTF-8' => ['manager-2', "Caf\xE9", 'A forced shipment has a reason: 1 to 255 characters on one line.'];
        yield 'a long reason' => ['manager-2', str_repeat('é', 256), 'A forced shipment has a reason: 1 to 255 characters on one line.'];
        yield 'an email address as the actor' => ['ada@example.com', 'Reason', 'is not the stable identifier of a actor'];
        yield 'no actor' => ['', 'Reason', 'is not the stable identifier of a actor'];
    }

    #[DataProvider('badForcings')]
    public function testAForcedShipmentIsChecked(string $actor, string $reason, string $message): void
    {
        $shipments = new Shipments($this->stock, $this->repository, new SwitchableAuthorization(false), new SwitchableAuthorization());

        try {
            $shipments->force($this->order(2_000), $actor, $reason, $this->at);
            self::fail($message);
        } catch (InvalidValue $invalidValue) {
            self::assertStringContainsString($message, $invalidValue->getMessage());
        }

        self::assertSame([], $this->repository->shipments);
    }

    public function testAReasonOfTheLongestLengthIsKept(): void
    {
        $reason = str_repeat('é', 255);

        self::assertSame($reason, Shipment::forced('1', ['product:A' => 1], $this->at, 'manager-2', $reason)->forceReason);
    }

    private function shipments(PaidShipmentAuthorization|SwitchableAuthorization $authorization): Shipments
    {
        return new Shipments($this->stock, $this->repository, $authorization);
    }

    private function payments(): Payments
    {
        return new Payments([], $this->paymentStore);
    }

    /**
     * @param int|null $refunded minor units refunded after the capture; `-1` leaves the payment uncaptured
     */
    private function pay(Order $order, int $amount, ?int $refunded): void
    {
        $payment = Payment::create($order, 'card', Money::of($amount, Currency::of('CAD')), $this->at);
        $this->paymentStore->add($payment);

        if (-1 === $refunded) {
            return;
        }

        $payment->apply(PaymentNotification::of('captured-'.$payment->id, $payment->id, PaymentEvent::Captured));

        if (null !== $refunded) {
            $payment->apply(PaymentNotification::of('refunded-'.$payment->id, $payment->id, PaymentEvent::Refunded, Money::of($refunded, Currency::of('CAD'))));
        }
    }

    private function order(int $total): Order
    {
        $unit = Money::of(intdiv($total, 2), Currency::of('CAD'));
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'DICE', $unit, 'goods'), 2, $unit);
        $order->place($this->at);

        return $order;
    }
}
