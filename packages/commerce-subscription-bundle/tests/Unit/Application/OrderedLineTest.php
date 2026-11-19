<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Application;

use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSubscriptionBundle\Application\OrderedLine;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderedLine::class)]
final class OrderedLineTest extends TestCase
{
    /**
     * The base is the subtotal with the options and after the promotions; the credit, the credits of the proration.
     */
    public function testALineIsWorthItsBaseLessItsCredits(): void
    {
        $cad = Currency::of('CAD');
        $line = SellableReference::of('subscription', 's-1');
        $order = Order::open('O-1', $cad);
        $order->add(Sellable::of($line, 'Pro', Money::of(1_000, $cad), 'digital'), 1, Money::of(1_000, $cad));
        $order->replaceAdjustments(AdjustmentType::Option, [Adjustment::of(AdjustmentType::Option, 'Extra', Money::of(200, $cad), $line)]);
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, 'Sale', Money::of(-100, $cad), $line)]);
        $order->replaceAdjustments(AdjustmentType::Proration, [
            Adjustment::of(AdjustmentType::Proration, 'Time', Money::of(-400, $cad), $line, origin: OrderedLine::UNUSED_TIME),
            Adjustment::of(AdjustmentType::Proration, 'Credit', Money::of(-300, $cad), $line, origin: OrderedLine::CHANGE_CREDIT),
            Adjustment::of(AdjustmentType::Proration, 'Carried', Money::of(-50, $cad), $line, origin: OrderedLine::CARRIED_CREDIT),
        ]);

        self::assertSame(1_100, OrderedLine::base($order, $line)->minorUnits);
        self::assertSame(350, OrderedLine::credit($order, $line)->minorUnits);
        self::assertSame(0, OrderedLine::base($order, SellableReference::of('subscription', 'other'))->minorUnits);
        self::assertSame(0, OrderedLine::credit($order, SellableReference::of('subscription', 'other'))->minorUnits);
    }
}
