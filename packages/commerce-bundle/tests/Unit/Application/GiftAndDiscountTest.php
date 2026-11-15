<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\GiftWrapping;
use Phil\CommerceBundle\Application\ManualDiscountProcessor;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Application\OrderMarkingStore;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryCommerceStore;
use Phil\CommerceBundle\Tests\Unit\Support\PricedProducts;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The gift wrapping and the discounts granted by hand, as order processors run by the modifier.
 */
#[CoversClass(GiftWrapping::class)]
#[CoversClass(ManualDiscountProcessor::class)]
#[CoversClass(OrderModifier::class)]
final class GiftAndDiscountTest extends TestCase
{
    public function testAWrappedOrderIsChargedOneWrappingAndOtherFeesStay(): void
    {
        $order = $this->order();
        $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Handling', $this->cad(200), origin: 'handling')]);

        $modifier = $this->modifier(new GiftWrapping(true, true, ['CAD' => '4.00'], ['CAD' => '3.50'], 'Wrap'));

        $modifier->offerAsGift($order, GiftOptions::of(true, message: 'Enjoy'));
        self::assertSame(['handling 200', 'gift_wrap 400 Wrap'], $this->fees($order));

        $modifier->offerAsGift($order, GiftOptions::of(true, true));
        self::assertSame(['handling 200', 'gift_wrap:recycled 350 Wrap'], $this->fees($order));

        $modifier->offerAsGift($order, GiftOptions::of(message: 'No wrap', pricesHidden: true));
        self::assertSame(['handling 200'], $this->fees($order));
        self::assertTrue($order->giftOptions()?->pricesHidden);

        $modifier->offerAsGift($order, null);
        self::assertNull($order->giftOptions());
    }

    public function testARecycledWrapCostsThePlainPriceWithoutItsOwnAndNothingWithoutAPrice(): void
    {
        $order = $this->order();
        $modifier = $this->modifier(new GiftWrapping(true, true, ['CAD' => '4.00']));

        $modifier->offerAsGift($order, GiftOptions::of(true, true));
        self::assertSame(['gift_wrap:recycled 400 Gift wrapping'], $this->fees($order));

        $free = $this->modifier(new GiftWrapping(true));
        $free->offerAsGift($order, GiftOptions::of(true));
        self::assertSame(['gift_wrap 0 Gift wrapping'], $this->fees($order));
    }

    public function testAStoreThatDoesNotWrapRefusesAWrappingButNotAMessage(): void
    {
        $order = $this->order();
        $modifier = $this->modifier(new GiftWrapping());

        $modifier->offerAsGift($order, GiftOptions::of(message: 'Hi'));
        self::assertSame('Hi', $order->giftOptions()?->message);
        self::assertSame([], $this->fees($order));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('This store does not wrap gifts.');

        $modifier->offerAsGift($order, GiftOptions::of(true));
    }

    public function testADisabledWrappingChargesNothingEvenForAStoredWrap(): void
    {
        $order = $this->order();
        $order->offerAsGift(GiftOptions::of(true));

        new GiftWrapping(false, true, ['CAD' => '4.00'])->process($order);

        self::assertSame([], $this->fees($order));
    }

    public function testAStoreWithoutARecycledWrapRefusesIt(): void
    {
        $modifier = $this->modifier(new GiftWrapping(true, false));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('This store does not offer a recycled wrap.');

        $modifier->offerAsGift($this->order(), GiftOptions::of(true, true));
    }

    public function testWithoutAWrappingPolicyNothingIsRefused(): void
    {
        $order = $this->order();
        $modifier = new OrderModifier([new PricedProducts()], [], []);

        $modifier->offerAsGift($order, GiftOptions::of(true, true));

        self::assertTrue($order->giftOptions()?->recycledWrap);
    }

    public function testADiscountOnALineIsTakenBeforeTheTaxes(): void
    {
        $order = $this->order();
        $modifier = $this->modifier();
        $modifier->changeTaxZone($order, 'ON');

        $modifier->grantManualDiscount($order, ManualDiscount::amount('box', $this->cad(200), 'Damaged box', 'clerk-1', SellableReference::of('product', 'dice')));

        self::assertSame(['manual:box product:dice -200 Damaged box'], $this->promotions($order));
        // 10.00 + 30.00 − 2.00, then 13 % on each line: 1.04 + 3.90.
        self::assertSame(3_800 + 104 + 390, $order->total()->minorUnits);

        $modifier->withdrawManualDiscount($order, 'box');
        self::assertSame([], $this->promotions($order));
    }

    public function testADiscountOnTheWholeOrderIsSharedByWhatIsLeftOfEachLine(): void
    {
        $order = $this->order();
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, 'Sale', $this->cad(-1_000), SellableReference::of('product', 'mat'), origin: 'promotion:sale')]);

        $modifier = $this->modifier();
        $modifier->grantManualDiscount($order, ManualDiscount::amount('all', $this->cad(1_000), 'Regular', 'clerk-1'));

        // What is left: dice 10.00, mat 20.00 once on sale; 10.00 shared one third, two thirds.
        self::assertSame(['promotion:sale product:mat -1000 Sale', 'manual:all product:dice -333 Regular', 'manual:all product:mat -667 Regular'], $this->promotions($order));
    }

    public function testDiscountsNeverBringALineBelowZero(): void
    {
        $order = $this->order();
        $dice = SellableReference::of('product', 'dice');
        $modifier = $this->modifier();

        $modifier->grantManualDiscount($order, ManualDiscount::amount('a', $this->cad(800), 'First', 'clerk-1', $dice));
        $modifier->grantManualDiscount($order, ManualDiscount::amount('b', $this->cad(800), 'Second', 'clerk-1', $dice));
        $modifier->grantManualDiscount($order, ManualDiscount::share('c', 5_000, 'Third', 'clerk-1', $dice));
        $modifier->grantManualDiscount($order, ManualDiscount::share('d', 10_000, 'All', 'clerk-1'));

        self::assertSame(['manual:a product:dice -800 First', 'manual:b product:dice -200 Second', 'manual:d product:mat -3000 All'], $this->promotions($order));
        self::assertTrue($order->total()->isZero());
    }

    public function testAWholeOrderDiscountOnNothingLeftTakesNothing(): void
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->grantManualDiscount(ManualDiscount::amount('all', $this->cad(100), 'R', 'clerk-1'));

        new ManualDiscountProcessor()->process($order);

        self::assertSame([], $this->promotions($order));
    }

    public function testADiscountOnALineRemovedSinceTakesNothing(): void
    {
        $order = $this->order();
        $order->grantManualDiscount(ManualDiscount::amount('pen', $this->cad(100), 'R', 'clerk-1', SellableReference::of('product', 'mat')));
        $order->remove(SellableReference::of('product', 'mat'));

        new ManualDiscountProcessor()->process($order);

        self::assertSame([], $this->promotions($order));
    }

    public function testPlacingGoesThroughTheWorkflowWhenThereIsOne(): void
    {
        $journal = new InMemoryCommerceStore();
        $lifecycle = new OrderLifecycle(OrderWorkflow::create(new OrderMarkingStore()), journal: $journal);
        $modifier = new OrderModifier([new PricedProducts()], [], [], lifecycle: $lifecycle);
        $order = $this->order();
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $modifier->place($order, $at, 'clerk-1');

        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame($at, $order->placedAt());
        self::assertCount(1, $journal->moves);
        self::assertSame('clerk-1', $journal->moves[0]->actor);
    }

    private function modifier(?GiftWrapping $wrapping = null): OrderModifier
    {
        $processors = [new ManualDiscountProcessor(), new TaxOrderProcessor(new ConfiguredTaxRates(['goods' => ['ON' => ['HST' => '13']]]))];

        if ($wrapping instanceof GiftWrapping) {
            $processors[] = $wrapping;
        }

        return new OrderModifier([new PricedProducts()], [], $processors, giftWrapping: $wrapping);
    }

    private function order(): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $modifier = new OrderModifier([new PricedProducts()], [], []);
        $context = PricingContext::of(1, new DateTimeImmutable());
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, $context);
        $modifier->add($order, SellableReference::of('product', 'mat'), 1, $context);

        return $order;
    }

    /**
     * @return list<string>
     */
    private function fees(Order $order): array
    {
        return array_map(static fn (Adjustment $fee): string => $fee->origin.' '.$fee->amount->minorUnits.('handling' === $fee->origin ? '' : ' '.$fee->label), $order->adjustments(AdjustmentType::Fee));
    }

    /**
     * @return list<string>
     */
    private function promotions(Order $order): array
    {
        return array_map(static fn (Adjustment $promotion): string => $promotion->origin.' '.$promotion->line?->value().' '.$promotion->amount->minorUnits.' '.$promotion->label, $order->adjustments(AdjustmentType::Promotion));
    }

    private function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }

    public function testAStoreThatWrapsOffersTheRecycledWrapUnlessToldNot(): void
    {
        $order = $this->order();

        $this->modifier(new GiftWrapping(true))->offerAsGift($order, GiftOptions::of(true, true));

        self::assertTrue($order->giftOptions()?->recycledWrap);
    }

    public function testADiscountOnTheWholeOrderSharesWhatIsLeftOnceTheOptionsAreAdded(): void
    {
        $order = $this->order();
        $dice = SellableReference::of('product', 'dice');
        $order->replaceAdjustments(AdjustmentType::Option, [Adjustment::of(AdjustmentType::Option, 'Engraving', $this->cad(500), $dice, origin: 'option:engraving')]);

        $modifier = $this->modifier();
        $modifier->grantManualDiscount($order, ManualDiscount::amount('all', $this->cad(1_000), 'Regular', 'clerk-1'));

        // Left: dice 10.00 + 5.00, mat 30.00; 10.00 shared 1,500 to 3,000.
        self::assertSame(['manual:all product:dice -333 Regular', 'manual:all product:mat -667 Regular'], $this->promotions($order));
    }

    public function testAPromotionOfNoLineIsKeptApartFromWhatIsLeftOfEachLine(): void
    {
        $order = $this->order();
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, 'Sale', $this->cad(-100), origin: 'promotion:sale')]);

        $modifier = $this->modifier();
        $modifier->grantManualDiscount($order, ManualDiscount::amount('box', $this->cad(200), 'Damaged box', 'clerk-1', SellableReference::of('product', 'dice')));

        self::assertSame(['promotion:sale  -100 Sale', 'manual:box product:dice -200 Damaged box'], $this->promotions($order));
    }

    public function testWhatAPromotionTookBeyondALineIsNotSharedBackToTheOthers(): void
    {
        $order = $this->order();
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, 'Big', $this->cad(-1_500), SellableReference::of('product', 'dice'), origin: 'promotion:big')]);

        $modifier = $this->modifier();
        $modifier->grantManualDiscount($order, ManualDiscount::amount('all', $this->cad(1_000), 'Regular', 'clerk-1'));

        self::assertSame(['promotion:big product:dice -1500 Big', 'manual:all product:mat -1000 Regular'], $this->promotions($order));
    }
}
