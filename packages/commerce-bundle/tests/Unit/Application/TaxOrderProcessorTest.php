<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\Exception\InvalidTaxRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxOrderProcessor::class)]
#[CoversClass(ConfiguredTaxRates::class)]
final class TaxOrderProcessorTest extends TestCase
{
    public function testEachLineIsTaxedByEachRateOfItsCategoryInTheZone(): void
    {
        $order = $this->order();
        $order->changeTaxZone('QC');

        $this->processor()->process($order);

        self::assertSame(
            [['product:dice', 'GST', 100], ['product:dice', 'QST', 199], ['download:pdf', 'GST', 50], ['download:pdf', 'QST', 100]],
            $this->taxes($order),
        );
        self::assertSame(2_999 + 449, $order->total()->minorUnits);
    }

    /**
     * At prices that include the taxes, they are taken out of each line: 19.99 is 17.39 before taxes, so GST 0.87 and
     * QST 1.73; 10.00 gives 0.44 and 0.87. The adjustments are neutral and the total is the prices on the tags.
     */
    public function testTaxesIncludedInThePricesAreTakenOutOfEachLine(): void
    {
        $order = $this->order();
        $order->changeTaxZone('QC');
        $order->changeTaxIncluded(true);

        $this->processor()->process($order);

        self::assertSame(
            [['product:dice', 'GST', 87], ['product:dice', 'QST', 173], ['download:pdf', 'GST', 44], ['download:pdf', 'QST', 87]],
            $this->taxes($order),
        );
        self::assertSame([true, true, true, true], array_map(static fn (Adjustment $tax): bool => $tax->neutral, $order->adjustments(AdjustmentType::Tax)));
        self::assertSame(2_999, $order->total()->minorUnits);
        self::assertSame(391, $order->includedTax()->minorUnits);

        $order->changeTaxIncluded(false);
        $this->processor()->process($order);
        self::assertSame([false, false, false, false], array_map(static fn (Adjustment $tax): bool => $tax->neutral, $order->adjustments(AdjustmentType::Tax)));
    }

    public function testACategoryWithoutRatesInTheZoneIsNotTaxed(): void
    {
        $order = $this->order();
        $order->changeTaxZone('ON');

        $this->processor()->process($order);

        self::assertSame([['product:dice', 'HST', 260]], $this->taxes($order));
    }

    /**
     * The tax is on the line after its promotions: 19.99 less 5.00 is 14.99, of which 5 % is 0.75.
     */
    public function testALineIsTaxedAfterItsPromotions(): void
    {
        $order = $this->order();
        $order->changeTaxZone('QC');

        $dice = SellableReference::of('product', 'dice');
        $order->replaceAdjustments(AdjustmentType::Promotion, [
            Adjustment::of(AdjustmentType::Promotion, 'Sale', Money::of(-300, Currency::of('CAD')), $dice),
            Adjustment::of(AdjustmentType::Promotion, 'Coupon', Money::of(-200, Currency::of('CAD')), $dice),
            Adjustment::of(AdjustmentType::Promotion, 'Order discount', Money::of(-100, Currency::of('CAD'))),
        ]);
        $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Tip', Money::of(1_000, Currency::of('CAD')), $dice)]);

        $this->processor()->process($order);

        self::assertSame([75, 150, 50, 100], array_map(static fn (array $tax): int => $tax[2], $this->taxes($order)));
    }

    /**
     * The proration on a line is part of its base, as its promotions are: 19.99 less 9.99 is 10.00, of which 5 % is
     * 0.50 and 9.975 % is 1.00.
     */
    public function testALineIsTaxedAfterItsProration(): void
    {
        $order = $this->order();
        $order->changeTaxZone('QC');

        $dice = SellableReference::of('product', 'dice');
        $order->replaceAdjustments(AdjustmentType::Proration, [
            Adjustment::of(AdjustmentType::Proration, 'Unused period', Money::of(-999, Currency::of('CAD')), $dice),
            Adjustment::of(AdjustmentType::Proration, 'Order credit', Money::of(-100, Currency::of('CAD'))),
        ]);

        $this->processor()->process($order);

        self::assertSame([50, 100, 50, 100], array_map(static fn (array $tax): int => $tax[2], $this->taxes($order)));
    }

    public function testAnOrderWithoutATaxZoneLosesItsTaxes(): void
    {
        $order = $this->order();
        $order->changeTaxZone('QC');
        $this->processor()->process($order);
        $order->changeTaxZone(null);

        $this->processor()->process($order);

        self::assertSame([], $order->adjustments(AdjustmentType::Tax));
        self::assertSame(2_999, $order->total()->minorUnits);
    }

    public function testRunningItTwiceChangesNothing(): void
    {
        $order = $this->order();
        $order->changeTaxZone('QC');
        $this->processor()->process($order);
        $first = $this->taxes($order);

        $this->processor()->process($order);

        self::assertSame($first, $this->taxes($order));
    }

    public function testConfiguredRatesAreReadFromTheirPercentages(): void
    {
        $rates = new ConfiguredTaxRates(['goods' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]);

        self::assertSame([50_000, 99_750], [$rates->ratesFor('goods', 'QC')[0]->partsPerMillion, $rates->ratesFor('goods', 'QC')[1]->partsPerMillion]);
        self::assertSame(['GST', 'QST'], [$rates->ratesFor('goods', 'QC')[0]->code, $rates->ratesFor('goods', 'QC')[1]->code]);
        self::assertSame([], $rates->ratesFor('goods', 'AB'));
        self::assertSame([], $rates->ratesFor('food', 'QC'));
        self::assertSame([], new ConfiguredTaxRates(['exempt' => ['QC' => []]])->ratesFor('exempt', 'QC'));
    }

    public function testAConfiguredRateARateCannotHoldIsRefused(): void
    {
        $this->expectException(InvalidTaxRate::class);

        new ConfiguredTaxRates(['goods' => ['QC' => ['GST' => '5,0']]]);
    }

    private function processor(): TaxOrderProcessor
    {
        return new TaxOrderProcessor(new ConfiguredTaxRates([
            'goods' => ['QC' => ['GST' => '5', 'QST' => '9.975'], 'ON' => ['HST' => '13']],
            'digital' => ['QC' => ['GST' => '5', 'QST' => '9.975']],
        ]));
    }

    private function order(): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open('1', $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', Money::of(1_999, $cad), 'goods'), 1, Money::of(1_999, $cad));
        $order->add(Sellable::of(SellableReference::of('download', 'pdf'), 'PDF', Money::of(1_000, $cad), 'digital'), 1, Money::of(1_000, $cad));

        return $order;
    }

    /**
     * @return list<array{string, string, int}>
     */
    private function taxes(Order $order): array
    {
        $taxes = [];

        foreach ($order->adjustments(AdjustmentType::Tax) as $adjustment) {
            $taxes[] = [$adjustment->line?->value() ?? '', $adjustment->label, $adjustment->amount->minorUnits];
        }

        return $taxes;
    }
}
