<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Change\OrderChangeState;
use Phil\CommerceBundle\Domain\Exception\OrderChangeRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderChange;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderChange::class)]
#[CoversClass(OrderChangeRefused::class)]
final class OrderChangeTest extends TestCase
{
    public function testAPreparedChangeShowsItsPreviewAndDifference(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $lines = [OrderChangeLine::of(SellableReference::of('product', 'dice'), 3)];
        $change = OrderChange::prepare($this->order(), $lines, 'Two more dice', 'clerk-1', $at, $this->cad(3_000));

        self::assertSame('2026-0001', $change->orderNumber);
        self::assertSame(1, $change->baseRevision);
        self::assertEquals($lines, $change->lines());
        self::assertSame('Two more dice', $change->reason);
        self::assertSame('clerk-1', $change->preparedBy);
        self::assertSame($at, $change->preparedAt);
        self::assertSame(1_000, $change->totalBefore->minorUnits);
        self::assertSame(3_000, $change->totalAfter->minorUnits);
        self::assertSame(2_000, $change->difference()->minorUnits);
        self::assertSame(OrderChangeState::Prepared, $change->state());
        self::assertNull($change->revision());
        self::assertNull($change->confirmedBy());
        self::assertNull($change->settledAt());
        self::assertSame(1, $change->version());
    }

    public function testAConfirmedChangeKeepsTheOrdersNewTotalAndRevision(): void
    {
        $change = OrderChange::prepare($this->order(), [OrderChangeLine::of(SellableReference::of('product', 'dice'), 0)], 'Removed', 'clerk-1', new DateTimeImmutable(), $this->cad(0));
        $at = new DateTimeImmutable('2026-11-11T11:00:00Z');

        $change->confirm(2, $this->cad(500), 'manager-1', $at);

        self::assertSame(OrderChangeState::Confirmed, $change->state());
        self::assertSame(2, $change->revision());
        self::assertSame(500, $change->totalAfter->minorUnits);
        self::assertSame(-500, $change->difference()->minorUnits);
        self::assertSame('manager-1', $change->confirmedBy());
        self::assertSame($at, $change->settledAt());

        $this->expectException(OrderChangeRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The change %s is confirmed: only a prepared change is confirmed or abandoned.', $change->id));

        $change->abandon($at);
    }

    public function testAnAbandonedChangeIsSettled(): void
    {
        $change = OrderChange::prepare($this->order(), [OrderChangeLine::of(SellableReference::of('product', 'dice'), 2)], 'More', 'clerk-1', new DateTimeImmutable(), $this->cad(2_000));
        $at = new DateTimeImmutable('2026-11-11T12:00:00Z');

        $change->abandon($at);

        self::assertSame(OrderChangeState::Abandoned, $change->state());
        self::assertSame($at, $change->settledAt());

        $this->expectException(OrderChangeRefused::class);

        $change->confirm(2, $this->cad(1), 'manager-1', $at);
    }

    public function testAChangeSetsEachLineOnce(): void
    {
        $dice = SellableReference::of('product', 'dice');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A change sets product:dice once.');

        OrderChange::prepare($this->order(), [OrderChangeLine::of($dice, 1), OrderChangeLine::of($dice, 2)], 'Twice', 'clerk-1', new DateTimeImmutable(), $this->cad(0));
    }

    public function testAChangeSetsOneTo500Lines(): void
    {
        $lines = [];

        for ($i = 0; $i < 500; ++$i) {
            $lines[] = OrderChangeLine::of(SellableReference::of('product', 'p'.$i), 1);
        }

        self::assertCount(500, OrderChange::prepare($this->order(), $lines, 'Many', 'clerk-1', new DateTimeImmutable(), $this->cad(0))->lines());

        foreach ([[], [...$lines, OrderChangeLine::of(SellableReference::of('product', 'more'), 1)]] as $refused) {
            try {
                OrderChange::prepare($this->order(), $refused, 'Bounds', 'clerk-1', new DateTimeImmutable(), $this->cad(0));
                self::fail('Refused: '.\count($refused));
            } catch (InvalidValue $error) {
                self::assertSame('A change sets 1 to 500 lines.', $error->getMessage());
            }
        }
    }

    public function testAChangeHasAReason(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A change has a reason: 1 to 255 characters on one line.');

        OrderChange::prepare($this->order(), [OrderChangeLine::of(SellableReference::of('product', 'dice'), 1)], "a\nb", 'clerk-1', new DateTimeImmutable(), $this->cad(0));
    }

    public function testTheRefusalsNameTheirOrder(): void
    {
        self::assertSame('The change c-1 was prepared on revision 1 of the order 2026-0001, which is at revision 2 now: prepare it again.', OrderChangeRefused::stale('c-1', '2026-0001', 1, 2)->getMessage());
        self::assertSame('The change c-1 is not a change of the order 2026-0002.', OrderChangeRefused::otherOrder('c-1', '2026-0002')->getMessage());
        self::assertSame('The order 2026-0001 has its receipt: a credit note and a new order replace it.', OrderChangeRefused::receiptIssued('2026-0001')->getMessage());
        self::assertSame('The order 2026-0001 is not placed: only a placed order is changed this way.', OrderChangeRefused::notPlaced('2026-0001')->getMessage());
    }

    private function order(): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $this->cad(1_000), 'goods'), 1, $this->cad(1_000));
        $order->place(new DateTimeImmutable());

        return $order;
    }

    private function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }
}
