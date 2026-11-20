<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommercePromotionsBundle\Application\Contract\CustomerOwnership;
use Phil\CommercePromotionsBundle\Application\FreeShippingProcessor;
use Phil\CommercePromotionsBundle\Application\PromotionEvaluator;
use Phil\CommercePromotionsBundle\Application\PromotionOrder;
use Phil\CommercePromotionsBundle\Application\PromotionProcessor;
use Phil\CommercePromotionsBundle\Application\Promotions;
use Phil\CommercePromotionsBundle\Domain\Exception\PromotionRefused;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\FixedClock;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\InMemoryPromotions;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the promotions give beyond a share, an amount or N for X: bundles, free shipping, exclusions, uses per
 * customer, owned products, incompatibilities and order.
 */
#[CoversClass(PromotionEvaluator::class)]
#[CoversClass(PromotionOrder::class)]
#[CoversClass(FreeShippingProcessor::class)]
#[CoversClass(Promotions::class)]
final class PromotionFeaturesTest extends TestCase
{
    private InMemoryPromotions $promotions;

    private FixedClock $clock;

    private ?CustomerOwnership $ownership = null;

    #[Override]
    protected function setUp(): void
    {
        $this->promotions = new InMemoryPromotions();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-11-28T12:00:00Z'));
    }

    /**
     * A rulebook and two dice for 40.00: the two cheapest dice fill the bundle, and each product keeps its own line
     * with its share of the 10.00 off, in proportion to what it brings to the bundle.
     */
    public function testABundleIsPricedAndEachProductKeepsItsLine(): void
    {
        $this->promotions->add($this->starter(4_000));
        $order = $this->order(['BOOK' => 1, 'D20' => 1, 'D6' => 3]);

        self::assertSame([['product:BOOK', 'Starter', -600], ['product:D6', 'Starter', -400]], $this->discounts($order));
        self::assertSame(['product:BOOK', 'product:D20', 'product:D6'], array_map(static fn (OrderLine $line): string => $line->reference->value(), $order->lines()));
    }

    /**
     * Two bundles take three D6 and the D20, the cheapest first: 105.00 of products for 80.00, 25.00 off spread over the
     * three lines, the unit left over going to the largest remainder.
     */
    public function testSeveralBundlesTakeTheCheapestUnitsFirst(): void
    {
        $this->promotions->add($this->starter(4_000));

        self::assertSame([['product:BOOK', 'Starter', -1_429], ['product:D6', 'Starter', -714], ['product:D20', 'Starter', -357]], $this->discounts($this->order(['BOOK' => 2, 'D6' => 3, 'D20' => 2])));
    }

    /**
     * Two dice of the same price: the earlier line fills the bundle first.
     */
    public function testEqualPricesFillTheBundleInTheirOrder(): void
    {
        $this->promotions->add(Promotion::bundlePrice('pair', 'Pair', [BundleGroup::of(['BOOK']), BundleGroup::of(['D6', 'D6B'])], Money::of(3_000, Currency::of('CAD'))));

        self::assertSame([['product:BOOK', 'Pair', -750], ['product:D6B', 'Pair', -250]], $this->discounts($this->order(['BOOK' => 1, 'D6B' => 1, 'D6' => 1])));
    }

    /**
     * A line the bundle does not hold, between two it does, changes nothing to what they get.
     */
    public function testALineOutsideTheBundleBetweenItsLinesIsLeftAlone(): void
    {
        $this->promotions->add($this->starter(4_000));

        self::assertSame([['product:BOOK', 'Starter', -600], ['product:D6', 'Starter', -400]], $this->discounts($this->order(['BOOK' => 1, 'PDF' => 1, 'D6' => 2])));
    }

    public function testAnIncompleteBundleGivesNothing(): void
    {
        $this->promotions->add($this->starter(4_000));

        self::assertSame([], $this->discounts($this->order(['D6' => 4, 'D20' => 1])));
        self::assertSame([], $this->discounts($this->order(['BOOK' => 3, 'D6' => 1])));
    }

    public function testABundleDearerThanItsProductsGivesNothing(): void
    {
        $this->promotions->add($this->starter(5_000));

        self::assertSame([], $this->discounts($this->order(['BOOK' => 1, 'D6' => 2])));

        $this->promotions->add($this->starter(4_999));
        self::assertSame([['product:BOOK', 'Starter', -1]], $this->discounts($this->order(['BOOK' => 1, 'D6' => 2])));
    }

    /**
     * A bundle comes after a share off: it prices what the share left, and never takes a line below zero.
     */
    public function testABundleTakesWhatThePromotionsBeforeLeft(): void
    {
        $this->promotions->add(Promotion::percentOff('half', 'Half', 500_000, Currency::of('CAD'))->when(skus: ['BOOK']));
        $this->promotions->add($this->starter(3_000)->when(after: ['half']));

        self::assertSame([['product:BOOK', 'Half', -1_500], ['product:BOOK', 'Starter', -214], ['product:D6', 'Starter', -286]], $this->discounts($this->order(['BOOK' => 1, 'D6' => 2])));

        $this->promotions->add($this->starter(4_000)->when(after: ['half']));
        self::assertSame([['product:BOOK', 'Half', -1_500]], $this->discounts($this->order(['BOOK' => 1, 'D6' => 2])));
    }

    /**
     * A bundle's minimum counts the products of its groups only.
     */
    public function testABundleMinimumCountsItsGroups(): void
    {
        $this->promotions->add($this->starter(4_000)->when(minimumSubtotal: Money::of(5_001, Currency::of('CAD'))));

        self::assertSame([], $this->discounts($this->order(['BOOK' => 1, 'D6' => 2, 'PDF' => 1])));
    }

    /**
     * The delivery charge of 15.00 is waived by a shipping adjustment of the same amount, under the label and the
     * origin of the promotion; the order carries the promotion as a neutral adjustment of zero.
     */
    public function testAFreeShippingWaivesTheDeliveryCharge(): void
    {
        $this->promotions->add(Promotion::freeShipping('ship', 'Free shipping', Currency::of('CAD'))->when(deliveryMethods: ['post'], minimumSubtotal: Money::of(5_000, Currency::of('CAD'))));
        $order = $this->order(['BOOK' => 2], method: 'post');

        self::assertSame([['', 'Free shipping', 0]], $this->discounts($order));
        self::assertTrue($order->adjustments(AdjustmentType::Promotion)[0]->neutral);
        self::assertSame([['Post', 1_500, null], ['Free shipping', -1_500, 'promotion:ship']], $this->shipping($order));
        self::assertTrue($order->adjustmentsTotal(AdjustmentType::Shipping)->isZero());
        self::assertSame(6_000, $order->total()->minorUnits);

        new FreeShippingProcessor()->process($order);
        self::assertSame([['Post', 1_500, null], ['Free shipping', -1_500, 'promotion:ship']], $this->shipping($order));
    }

    public function testAFreeShippingNeedsItsMethodAndItsMinimum(): void
    {
        $this->promotions->add(Promotion::freeShipping('ship', 'Free shipping', Currency::of('CAD'))->when(deliveryMethods: ['post'], minimumSubtotal: Money::of(5_000, Currency::of('CAD'))));

        self::assertSame([['Courier', 3_000, null]], $this->shipping($this->order(['BOOK' => 2], method: 'courier')));
        self::assertSame([['Post', 1_500, null]], $this->shipping($this->order(['BOOK' => 1], method: 'post')));
        self::assertSame([], $this->shipping($this->order(['BOOK' => 2])));
        self::assertSame([], $this->discounts($this->order(['BOOK' => 2])));
    }

    /**
     * Without a delivery charge there is nothing to waive; a neutral charge is shown, never waived.
     */
    public function testAFreeDeliveryIsLeftAsItIs(): void
    {
        $this->promotions->add(Promotion::freeShipping('ship', 'Free shipping', Currency::of('CAD')));

        self::assertSame([['Pickup', 0, null]], $this->shipping($this->order(['BOOK' => 1], method: 'pickup')));
        self::assertSame([['Included', 900, null]], $this->shipping($this->order(['BOOK' => 1], method: 'included')));
    }

    /**
     * Only the first free shipping waives the charge; a second one only shows on the order.
     */
    public function testTheFirstFreeShippingWaives(): void
    {
        $this->promotions->add(Promotion::freeShipping('a-ship', 'First', Currency::of('CAD')));
        $this->promotions->add(Promotion::freeShipping('b-ship', 'Second', Currency::of('CAD')));

        self::assertSame([['Post', 1_500, null], ['First', -1_500, 'promotion:a-ship']], $this->shipping($this->order(['BOOK' => 1], method: 'post')));
    }

    /**
     * An adjustment of zero on the order that no promotion gave, or that is not neutral, waives nothing.
     */
    public function testOnlyThePromotionsNeutralAdjustmentWaives(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('8', $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'BOOK'), 'Book', Money::of(3_000, $cad), 'goods'), 1, Money::of(3_000, $cad));
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Post', Money::of(1_500, $cad))]);

        $processor = new FreeShippingProcessor();

        foreach ([
            Adjustment::of(AdjustmentType::Promotion, 'Other', Money::zero($cad), neutral: true, origin: 'manual:7'),
            Adjustment::of(AdjustmentType::Promotion, 'Loud', Money::zero($cad), origin: 'promotion:loud'),
            Adjustment::of(AdjustmentType::Promotion, 'Line', Money::zero($cad), SellableReference::of('product', 'BOOK'), neutral: true, origin: 'promotion:line'),
            Adjustment::of(AdjustmentType::Promotion, 'Some', Money::of(-1, $cad), neutral: true, origin: 'promotion:some'),
        ] as $adjustment) {
            $order->replaceAdjustments(AdjustmentType::Promotion, [$adjustment]);
            $processor->process($order);
            self::assertSame([['Post', 1_500, null]], $this->shipping($order));
        }
    }

    public function testFreeShippingIsCountedLikeAnyPromotion(): void
    {
        $this->promotions->add(Promotion::freeShipping('ship', 'Free shipping', Currency::of('CAD')));
        $order = $this->order(['BOOK' => 1], method: 'post');
        $order->place($this->clock->now);

        self::assertSame(['ship'], array_map(static fn (PromotionRedemption $redemption): string => $redemption->promotion, new Promotions($this->promotions)->redeem($order)));
    }

    /**
     * An excluded product keeps its price, and an amount off is spread over the others in proportion.
     */
    public function testAnExcludedProductIsNeverDiscounted(): void
    {
        $this->promotions->add(Promotion::amountOff('five', 'Five', Money::of(500, Currency::of('CAD')))->when(excludedSkus: ['BOOK']));

        self::assertSame([['product:D6', 'Five', -200], ['product:D20', 'Five', -300]], $this->discounts($this->order(['BOOK' => 1, 'D6' => 1, 'D20' => 1])));
    }

    /**
     * A promotion limited per customer is refused to a guest and to a customer who used it up, and counted for the
     * customer once the order is placed.
     */
    public function testUsesAreCountedPerCustomer(): void
    {
        $this->promotions->add(Promotion::percentOff('welcome', 'Welcome', 100_000, Currency::of('CAD'))->when(perCustomerLimit: 1));

        self::assertSame([], $this->discounts($this->order(['PDF' => 1])));

        $order = $this->order(['PDF' => 1], customer: 'customer-7');
        self::assertSame([['product:PDF', 'Welcome', -100]], $this->discounts($order));

        $order->place($this->clock->now);
        $redeemed = new Promotions($this->promotions)->redeem($order);
        self::assertSame('customer-7', $redeemed[0]->customer);
        self::assertSame(1, $this->promotions->customerUses('welcome', 'customer-7'));
        self::assertSame([], $this->discounts($this->order(['PDF' => 1], customer: 'customer-7')));
        self::assertSame([['product:PDF', 'Welcome', -100]], $this->discounts($this->order(['PDF' => 1], customer: 'customer-8')));
    }

    /**
     * Two orders of the same customer took the last use before either was placed: the second placement is refused.
     */
    public function testTheLastUseOfACustomerGoesToOneOrder(): void
    {
        $this->promotions->add(Promotion::percentOff('welcome', 'Welcome', 100_000, Currency::of('CAD'))->when(perCustomerLimit: 1));
        $first = $this->order(['PDF' => 1], customer: 'customer-7');
        $second = $this->order(['PDF' => 1], customer: 'customer-7', number: '2');
        $first->place($this->clock->now);
        $second->place($this->clock->now);
        $promotions = new Promotions($this->promotions);
        $promotions->redeem($first);

        $this->expectExceptionObject(PromotionRefused::exhaustedForCustomer('welcome'));

        $promotions->redeem($second);
    }

    /**
     * A promotion without a limit per customer counts no customer, and a guest's order counts none either.
     */
    public function testOnlyALimitPerCustomerCountsTheCustomer(): void
    {
        $this->promotions->add(Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD')));
        $order = $this->order(['PDF' => 1], customer: 'customer-7');
        $order->place($this->clock->now);
        new Promotions($this->promotions)->redeem($order);

        self::assertSame([], $this->promotions->customerUses);
        self::assertSame('customer-7', $this->promotions->redemptions['1 sale']->customer);
    }

    /**
     * A promotion withdrawn since the order was placed is still counted, for the customer too.
     */
    public function testAPromotionWithdrawnSincePlacementIsCountedForTheCustomer(): void
    {
        $welcome = Promotion::percentOff('welcome', 'Welcome', 100_000, Currency::of('CAD'))->when(perCustomerLimit: 1);
        $this->promotions->add($welcome);
        $order = $this->order(['PDF' => 1], customer: 'customer-7');
        $order->place($this->clock->now);

        $welcome->withdraw();

        self::assertCount(1, new Promotions($this->promotions)->redeem($order));
        self::assertSame(1, $this->promotions->customerUses('welcome', 'customer-7'));
    }

    /**
     * A promotion for the owners of a product applies to a customer the application says owns one, never to a guest,
     * and never without an application to ask.
     */
    public function testAPromotionForOwnersAsksTheApplication(): void
    {
        $this->promotions->add(Promotion::percentOff('owners', 'Owners', 200_000, Currency::of('CAD'))->when(ownedSkus: ['CORE', 'STARTER']));

        self::assertSame([], $this->discounts($this->order(['PDF' => 1], customer: 'customer-7')));

        $this->ownership = new class implements CustomerOwnership {
            /** @var list<array{string, list<string>}> */
            public array $asked = [];

            #[Override]
            public function ownsAny(string $customer, array $skus): bool
            {
                $this->asked[] = [$customer, $skus];

                return 'customer-7' === $customer;
            }
        };

        self::assertSame([['product:PDF', 'Owners', -200]], $this->discounts($this->order(['PDF' => 1], customer: 'customer-7')));
        self::assertSame([], $this->discounts($this->order(['PDF' => 1], customer: 'customer-8')));
        self::assertSame([], $this->discounts($this->order(['PDF' => 1])));
        self::assertSame(['customer-7', ['CORE', 'STARTER']], $this->ownership->asked[0]);
    }

    /**
     * Two incompatible promotions never combine: the first that applies wins, whichever lists the other, and the
     * others still apply.
     */
    public function testIncompatiblePromotionsNeverCombine(): void
    {
        $this->promotions->add(Promotion::percentOff('a-first', 'First', 100_000, Currency::of('CAD')));
        $this->promotions->add(Promotion::percentOff('b-second', 'Second', 100_000, Currency::of('CAD'))->when(incompatibleWith: ['a-first']));
        $this->promotions->add(Promotion::percentOff('c-third', 'Third', 100_000, Currency::of('CAD'))->when(incompatibleWith: ['z-absent']));

        self::assertSame([['product:PDF', 'First', -100], ['product:PDF', 'Third', -90]], $this->discounts($this->order(['PDF' => 1])));
    }

    /**
     * An incompatible promotion that gave nothing stops nothing.
     */
    public function testAnIncompatiblePromotionThatGaveNothingStopsNothing(): void
    {
        $this->promotions->add(Promotion::percentOff('a-first', 'First', 100_000, Currency::of('CAD'))->when(skus: ['MEEPLE']));
        $this->promotions->add(Promotion::percentOff('b-second', 'Second', 100_000, Currency::of('CAD'))->when(incompatibleWith: ['a-first']));

        self::assertSame([['product:PDF', 'Second', -100]], $this->discounts($this->order(['PDF' => 1])));
    }

    /**
     * Promotions come by code, each moved after those its list names, depth first; a name that is no promotion is
     * ignored, and a loop is set aside for the order by code.
     */
    public function testPromotionsComeInTheOrderTheirListsSet(): void
    {
        $cad = Currency::of('CAD');
        $codes = static fn (array $promotions): array => array_map(static fn (Promotion $promotion): string => $promotion->code, PromotionOrder::sort($promotions));

        self::assertSame(['a', 'b', 'c'], $codes([Promotion::percentOff('c', 'C', 1, $cad), Promotion::percentOff('a', 'A', 1, $cad), Promotion::percentOff('b', 'B', 1, $cad)]));
        self::assertSame(['c', 'a', 'b'], $codes([Promotion::percentOff('a', 'A', 1, $cad)->when(after: ['c', 'nowhere']), Promotion::percentOff('b', 'B', 1, $cad), Promotion::percentOff('c', 'C', 1, $cad)]));
        self::assertSame(['a', 'b', 'c'], $codes([Promotion::percentOff('a', 'A', 1, $cad)->when(after: ['b']), Promotion::percentOff('b', 'B', 1, $cad)->when(after: ['a']), Promotion::percentOff('c', 'C', 1, $cad)]));
        self::assertSame(['1', '2', '10'], $codes([Promotion::percentOff('10', 'Ten', 1, $cad)->when(after: ['2']), Promotion::percentOff('2', 'Two', 1, $cad), Promotion::percentOff('1', 'One', 1, $cad)]));
        self::assertSame([], PromotionOrder::sort([]));
    }

    /**
     * The order set by the lists decides which promotion takes what is left: here the amount off comes first.
     */
    public function testTheOrderDecidesWhatIsLeft(): void
    {
        $this->promotions->add(Promotion::percentOff('a-half', 'Half', 500_000, Currency::of('CAD'))->when(after: ['b-five']));
        $this->promotions->add(Promotion::amountOff('b-five', 'Five', Money::of(500, Currency::of('CAD'))));

        self::assertSame([['product:PDF', 'Five', -500], ['product:PDF', 'Half', -250]], $this->discounts($this->order(['PDF' => 1])));
    }

    private function starter(int $price): Promotion
    {
        return Promotion::bundlePrice('starter', 'Starter', [BundleGroup::of(['BOOK']), BundleGroup::of(['D6', 'D20'], 2)], Money::of($price, Currency::of('CAD')));
    }

    /**
     * @param array<string, int> $lines SKU → quantity
     */
    private function order(array $lines, ?string $method = null, ?string $customer = null, string $number = '1'): Order
    {
        $cad = static fn (int $minorUnits): Money => Money::of($minorUnits, Currency::of('CAD'));
        $shipping = new class implements OrderProcessor {
            #[Override]
            public function process(Order $order): void
            {
                $cad = static fn (int $minorUnits): Money => Money::of($minorUnits, Currency::of('CAD'));
                $order->replaceAdjustments(AdjustmentType::Shipping, match ($order->deliveryMethod()) {
                    'post' => [Adjustment::of(AdjustmentType::Shipping, 'Post', $cad(1_500))],
                    'courier' => [Adjustment::of(AdjustmentType::Shipping, 'Courier', $cad(3_000))],
                    'pickup' => [Adjustment::of(AdjustmentType::Shipping, 'Pickup', $cad(0))],
                    'included' => [Adjustment::of(AdjustmentType::Shipping, 'Included', $cad(900), neutral: true)],
                    default => [],
                });
            }
        };
        $modifier = new OrderModifier(
            [new ProductSellables(new InMemoryProducts(
                Product::create('BOOK', ProductKind::Physical, 'Rulebook', $cad(3_000), 'goods'),
                Product::create('D6', ProductKind::Physical, 'D6', $cad(1_000), 'goods'),
                Product::create('D6B', ProductKind::Physical, 'D6 blue', $cad(1_000), 'goods'),
                Product::create('D20', ProductKind::Physical, 'D20', $cad(1_500), 'goods'),
                Product::create('PDF', ProductKind::Digital, 'PDF', $cad(1_000), 'goods'),
            ))],
            [],
            [new PromotionProcessor(new PromotionEvaluator($this->promotions, ownership: $this->ownership), $this->clock), $shipping, new FreeShippingProcessor(), new TaxOrderProcessor(new ConfiguredTaxRates(['goods' => ['QC' => ['GST' => '5', 'QST' => '10']]]))],
        );
        $order = Order::open($number, Currency::of('CAD'));
        $order->assignCustomer($customer);

        $context = PricingContext::of(1, $this->clock->now);

        foreach ($lines as $sku => $quantity) {
            $modifier->add($order, SellableReference::of('product', $sku), $quantity, $context);
        }

        $modifier->chooseDeliveryMethod($order, $method);

        return $order;
    }

    /**
     * @return list<array{string, string, int}>
     */
    private function discounts(Order $order): array
    {
        return array_map(static fn (Adjustment $adjustment): array => [$adjustment->line?->value() ?? '', $adjustment->label, $adjustment->amount->minorUnits], $order->adjustments(AdjustmentType::Promotion));
    }

    /**
     * @return list<array{string, int, ?string}>
     */
    private function shipping(Order $order): array
    {
        return array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->origin], $order->adjustments(AdjustmentType::Shipping));
    }
}
