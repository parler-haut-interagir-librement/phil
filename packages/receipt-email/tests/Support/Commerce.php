<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Receipts of `phil/commerce-bundle`, issued from placed orders as the core issues them.
 */
final class Commerce
{
    /**
     * Tea ×3 at 5.00 and a configured teapot at 30.00, GST and QST on each line, delivery 10.00, a discount of 5.00.
     */
    public static function order(bool $taxIncluded = false): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-1001', $cad);
        $tea = Sellable::of(SellableReference::of('product', 'TEA'), 'Tea', Money::of(500, $cad), 'standard');
        $teapot = Sellable::of(SellableReference::of('product', 'POT'), 'Teapot', Money::of(3_000, $cad), 'standard', OptionSet::of(
            OptionDefinition::choice('colour', 'Colour', [OptionChoice::of('blue', 'Blue')]),
            OptionDefinition::draw('glaze', 'Glaze', 'dice'),
        ));
        $teaLine = $order->add($tea, 3, Money::of(500, $cad));
        $potLine = $order->add($teapot, 1, Money::of(3_000, $cad), LineConfiguration::of(['colour' => OptionValue::chosen('blue')]));
        $order->changeTaxIncluded($taxIncluded);
        $order->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(75, $cad), $teaLine->reference, $taxIncluded),
            Adjustment::of(AdjustmentType::Tax, 'QST', Money::of(150, $cad), $teaLine->reference, $taxIncluded),
            Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(150, $cad), $potLine->reference, $taxIncluded),
            Adjustment::of(AdjustmentType::Tax, 'QST', Money::of(299, $cad), $potLine->reference, $taxIncluded),
        ]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(1_000, $cad))]);
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, 'Welcome', Money::of(-500, $cad), $teaLine->reference)]);
        $order->place(new DateTimeImmutable('2026-11-20 14:30:00'));

        return $order;
    }

    public static function receipt(bool $taxIncluded = false): Receipt
    {
        return Receipt::issue('R-000042', self::order($taxIncluded), self::issuer(), new DateTimeImmutable('2026-11-20 14:30:00'));
    }

    public static function creditNote(): Receipt
    {
        return Receipt::credit('C-000007', self::receipt(), [], new DateTimeImmutable('2026-11-21 09:00:00'));
    }

    public static function issuer(): Issuer
    {
        return Issuer::of('The Shop', ['12 Main Street', 'Montréal QC  H2X 1Y4'], ['GST' => '123456789 RT0001', 'QST' => '1234567890 TQ0001']);
    }
}
