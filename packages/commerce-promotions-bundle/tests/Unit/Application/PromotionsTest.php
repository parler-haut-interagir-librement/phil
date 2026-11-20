<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommercePromotionsBundle\Application\PromotionEvaluator;
use Phil\CommercePromotionsBundle\Application\PromotionProcessor;
use Phil\CommercePromotionsBundle\Application\Promotions;
use Phil\CommercePromotionsBundle\Domain\Exception\PromotionRefused;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\FixedClock;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\InMemoryPromotions;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(PromotionEvaluator::class)]
#[CoversClass(PromotionProcessor::class)]
#[CoversClass(Promotions::class)]
#[CoversClass(PromotionRefused::class)]
final class PromotionsTest extends TestCase
{
    private InMemoryPromotions $promotions;

    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->promotions = new InMemoryPromotions();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-11-28T12:00:00Z'));
    }

    /**
     * 10 % off the dice: 2 × 20.00 is 40.00, less 4.00; the tax is on 36.00.
     */
    public function testAShareOffIsTakenBeforeTheTax(): void
    {
        $this->promotions->add(Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD'))->when(skus: ['DICE']));
        $order = $this->order(['DICE' => 2, 'PDF' => 1]);

        self::assertSame([['product:DICE', 'Sale', -400]], $this->discounts($order));
        self::assertSame([180, 359, 50, 100], array_map(static fn (Adjustment $tax): int => $tax->amount->minorUnits, $order->adjustments(AdjustmentType::Tax)));
        self::assertSame(5_000 - 400 + 689, $order->total()->minorUnits);
    }

    /**
     * 5.00 off an order of 40.00 and 10.00, spread 4.00 and 1.00.
     */
    public function testAnAmountOffIsSpreadOverTheLines(): void
    {
        $this->promotions->add(Promotion::amountOff('five', 'Five off', Money::of(500, Currency::of('CAD'))));

        self::assertSame([['product:DICE', 'Five off', -400], ['product:PDF', 'Five off', -100]], $this->discounts($this->order(['DICE' => 2, 'PDF' => 1])));
    }

    public function testAnAmountOffNeverExceedsTheLines(): void
    {
        $this->promotions->add(Promotion::amountOff('big', 'Big', Money::of(100_000, Currency::of('CAD'))));
        $order = $this->order(['PDF' => 1]);

        self::assertSame([['product:PDF', 'Big', -1_000]], $this->discounts($order));
        self::assertTrue($order->total()->isZero());
    }

    /**
     * Three for two on 7 units: two groups, two units free.
     */
    public function testBuyNPayXGivesUnitsFree(): void
    {
        $this->promotions->add(Promotion::buyNPayX('three-for-two', '3 for 2', 3, 2, Currency::of('CAD')));

        self::assertSame([['product:DICE', '3 for 2', -4_000]], $this->discounts($this->order(['DICE' => 7, 'PDF' => 2])));
    }

    /**
     * Promotions stack in the order their `after` lists set, each on what the ones before left, never below zero; an exclusive one that gives
     * something stops the rest.
     */
    public function testPromotionsStackInTheirOrderUntilAnExclusiveOne(): void
    {
        $this->promotions->add(Promotion::percentOff('half', 'Half', 500_000, Currency::of('CAD')));
        $this->promotions->add(Promotion::amountOff('fifteen', 'Fifteen', Money::of(1_500, Currency::of('CAD')))->when(skus: ['DICE'], after: ['half'], exclusive: true));
        $this->promotions->add(Promotion::percentOff('late', 'Late', 100_000, Currency::of('CAD')));

        self::assertSame([['product:DICE', 'Half', -1_000], ['product:PDF', 'Half', -500], ['product:DICE', 'Fifteen', -1_000]], $this->discounts($this->order(['DICE' => 1, 'PDF' => 1])));
    }

    /**
     * A promotion that does not apply, or lacks its code, leaves the next ones to try.
     */
    public function testAPromotionThatDoesNotApplyLeavesTheOthers(): void
    {
        $this->promotions->add(Promotion::percentOff('store', 'Store', 500_000, Currency::of('CAD'))->when(channels: ['store']));
        $this->promotions->add(Promotion::percentOff('coded', 'Coded', 500_000, Currency::of('CAD'))->when(requiresCode: true));
        $this->promotions->add(Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD')));

        self::assertSame([['product:PDF', 'Sale', -100]], $this->discounts($this->order(['PDF' => 1])));
    }

    /**
     * An amount off on some products only is spread over those lines, wherever they stand in the order.
     */
    public function testAnAmountOffOnSomeProductsSpreadsOverThem(): void
    {
        $this->promotions->add(Promotion::amountOff('dice', 'Dice off', Money::of(300, Currency::of('CAD')))->when(skus: ['DICE', 'D6']));

        self::assertSame([['product:DICE', 'Dice off', -200], ['product:D6', 'Dice off', -100]], $this->discounts($this->order(['PDF' => 1, 'DICE' => 1, 'D6' => 1])));
    }

    /**
     * A share is exact on large amounts: half of 10 000 000 000.00 is 5 000 000 000.00.
     */
    public function testAShareIsExactOnLargeAmounts(): void
    {
        $this->promotions->add(Promotion::percentOff('half', 'Half', 500_000, Currency::of('CAD')));

        self::assertSame([['product:VAULT', 'Half', -500_000_000_000]], $this->discounts($this->order(['VAULT' => 1])));
    }

    public function testAnExclusivePromotionThatGivesNothingStopsNothing(): void
    {
        $this->promotions->add(Promotion::percentOff('meeple', 'Meeple', 500_000, Currency::of('CAD'))->when(skus: ['MEEPLE'], exclusive: true));
        $this->promotions->add(Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD')));

        self::assertSame([['product:PDF', 'Sale', -100]], $this->discounts($this->order(['PDF' => 1])));
    }

    public function testAMinimumIsReachedByTheEligibleLines(): void
    {
        $this->promotions->add(Promotion::amountOff('min', 'Min', Money::of(500, Currency::of('CAD')))->when(skus: ['DICE'], minimumSubtotal: Money::of(4_000, Currency::of('CAD'))));

        self::assertSame([], $this->discounts($this->order(['DICE' => 1, 'PDF' => 5])));
        self::assertSame([['product:DICE', 'Min', -500]], $this->discounts($this->order(['DICE' => 2])));
    }

    public function testAPromotionAsksForItsCode(): void
    {
        $promotion = Promotion::percentOff('code', 'Coded', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $this->promotions->add($promotion);
        $this->promotions->addCode(PromotionCode::of('SAVE10', $promotion, 1));
        $this->promotions->addCode(PromotionCode::of('OTHER', Promotion::percentOff('other', 'Other', 1, Currency::of('CAD'))));

        self::assertSame([], $this->discounts($this->order(['PDF' => 1])));
        self::assertSame([], $this->discounts($this->order(['PDF' => 1], ['OTHER', 'NOPE'])));
        self::assertSame([['product:PDF', 'Coded', -100]], $this->discounts($this->order(['PDF' => 1], ['NOPE', 'SAVE10'])));

        $this->promotions->codes['SAVE10']->claim();
        self::assertSame([], $this->discounts($this->order(['PDF' => 1], ['SAVE10'])));
    }

    public function testThePromotionsConditionsReadTheOrder(): void
    {
        $this->promotions->add(Promotion::percentOff('web-vip', 'Web VIP', 100_000, Currency::of('CAD'))->when(channels: ['web'], customerGroups: ['vip'], endsAt: new DateTimeImmutable('2026-12-01T00:00:00Z')));

        self::assertSame([], $this->discounts($this->order(['PDF' => 1])));
        self::assertSame([['product:PDF', 'Web VIP', -100]], $this->discounts($this->order(['PDF' => 1], [], 'web', 'vip')));

        $this->clock->now = new DateTimeImmutable('2026-12-01T00:00:00Z');
        self::assertSame([], $this->discounts($this->order(['PDF' => 1], [], 'web', 'vip')));
    }

    public function testOnlyProductsAreDiscounted(): void
    {
        $this->promotions->add(Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD')));
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('offer', 'gold'), 'Gold', Money::of(1_000, Currency::of('CAD')), 'digital'), 1, Money::of(1_000, Currency::of('CAD')));

        new PromotionProcessor(new PromotionEvaluator($this->promotions), $this->clock)->process($order);

        self::assertSame([], $order->adjustments(AdjustmentType::Promotion));
    }

    public function testAPromotionDropsWhenItNoLongerApplies(): void
    {
        $promotion = Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD'));
        $this->promotions->add($promotion);
        $order = $this->order(['PDF' => 1]);
        self::assertCount(1, $order->adjustments(AdjustmentType::Promotion));

        $promotion->withdraw();
        new PromotionProcessor(new PromotionEvaluator($this->promotions), $this->clock)->process($order);

        self::assertSame([], $order->adjustments(AdjustmentType::Promotion));
    }

    public function testCodesAreGeneratedInABatch(): void
    {
        $promotion = Promotion::percentOff('spring', 'Spring', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $this->promotions->addCode(PromotionCode::of('SPRING-TAKEN', $promotion));
        $promotions = new Promotions($this->promotions, new Randomizer(new Xoshiro256StarStar(42)));

        $codes = $promotions->generateCodes($promotion, 50, 'SPRING-');

        self::assertCount(50, $codes);
        self::assertSame(range(0, 49), array_keys($codes));
        self::assertCount(51, $this->promotions->codes);
        self::assertCount(50, array_unique(array_map(static fn (PromotionCode $code): string => $code->code, $codes)));

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^SPRING-[2-9A-HJKMNP-Z]{8}$/', $code->code);
            self::assertSame('spring', $code->promotion);
            self::assertSame(1, $code->usageLimit);
        }

        self::assertNull($promotions->generateCodes($promotion, 1, 'OPEN-', null)[0]->usageLimit);
    }

    /**
     * A tiny alphabet of draws repeats itself: the batch skips the codes already drawn or stored.
     */
    public function testABatchSkipsCodesAlreadyTaken(): void
    {
        $promotion = Promotion::percentOff('spring', 'Spring', 100_000, Currency::of('CAD'));
        $draw = new Randomizer(new Xoshiro256StarStar(7));
        $first = new Promotions($this->promotions, $draw)->generateCodes($promotion, 1, 'A-');
        $again = new Promotions($this->promotions, new Randomizer(new Xoshiro256StarStar(7)))->generateCodes($promotion, 2, 'A-');

        self::assertNotContains($first[0]->code, array_map(static fn (PromotionCode $code): string => $code->code, $again));
        self::assertCount(3, $this->promotions->codes);
    }

    public function testABatchHoldsOneToTenThousandCodes(): void
    {
        $promotions = new Promotions($this->promotions);
        $promotion = Promotion::percentOff('spring', 'Spring', 100_000, Currency::of('CAD'));

        foreach ([0, 10_001] as $count) {
            try {
                $promotions->generateCodes($promotion, $count, 'X-');
                self::fail('The batch was generated.');
            } catch (PromotionRefused $promotionRefused) {
                self::assertSame(\sprintf('A batch holds 1 to 10000 codes; %d does not.', $count), $promotionRefused->getMessage());
            }
        }

        self::assertCount(10_000, $promotions->generateCodes($promotion, 10_000, 'X-', null));
    }

    public function testAPlacedOrderCountsTheUsesOfItsPromotionsAndCodes(): void
    {
        $coded = Promotion::percentOff('coded', 'Coded', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $open = Promotion::percentOff('open', 'Open', 100_000, Currency::of('CAD'));
        $missed = Promotion::percentOff('missed', 'Missed', 100_000, Currency::of('CAD'))->when(skus: ['MEEPLE']);
        $this->promotions->add($coded);
        $this->promotions->add($open);
        $this->promotions->add($missed);

        $code = PromotionCode::of('SAVE10', $coded);
        $this->promotions->addCode($code);
        $order = $this->order(['PDF' => 1], ['SAVE10']);
        $order->place(new DateTimeImmutable('2026-11-28T12:00:00Z'));

        $redeemed = new Promotions($this->promotions)->redeem($order);

        self::assertSame([1, 1, 0], [$coded->uses(), $open->uses(), $missed->uses()]);
        self::assertSame(1, $code->uses());
        self::assertSame([['1', 'coded', 'SAVE10'], ['1', 'open', null]], array_map(static fn (PromotionRedemption $redemption): array => [$redemption->orderNumber, $redemption->promotion, $redemption->code], $redeemed));
    }

    /**
     * A retried job, or an order reopened and placed again: each promotion is counted once for the order.
     */
    public function testAnOrderIsCountedOnce(): void
    {
        $open = Promotion::percentOff('open', 'Open', 100_000, Currency::of('CAD'));
        $this->promotions->add($open);
        $order = $this->order(['PDF' => 1]);
        $order->place(new DateTimeImmutable('2026-11-28T12:00:00Z'));

        $promotions = new Promotions($this->promotions);

        $promotions->redeem($order);

        self::assertSame([], $promotions->redeem($order));
        self::assertSame(1, $open->uses());
    }

    /**
     * What the order got when it was placed is counted, even when the promotion was withdrawn since; a promotion
     * that started since is not.
     */
    public function testWhatTheOrderGotIsCountedNotWhatAppliesNow(): void
    {
        $withdrawn = Promotion::percentOff('withdrawn', 'Withdrawn', 100_000, Currency::of('CAD'));
        $this->promotions->add($withdrawn);
        $order = $this->order(['PDF' => 1]);
        $order->place(new DateTimeImmutable('2026-11-28T12:00:00Z'));

        $withdrawn->withdraw();
        $later = Promotion::percentOff('later', 'Later', 100_000, Currency::of('CAD'));
        $this->promotions->add($later);

        new Promotions($this->promotions)->redeem($order);

        self::assertSame([1, 0], [$withdrawn->uses(), $later->uses()]);
    }

    /**
     * The promotion was deleted once its use for the order was claimed: the redemption is kept, with no limit per
     * customer left to read.
     */
    public function testAPromotionDeletedOnceItsUseIsClaimedIsStillRedeemed(): void
    {
        $this->promotions->add(Promotion::percentOff('gone', 'Gone', 100_000, Currency::of('CAD')));
        $this->promotions->deletedOnClaim[] = 'gone';
        $order = $this->order(['PDF' => 1]);
        $order->place(new DateTimeImmutable('2026-11-28T12:00:00Z'));

        $redeemed = new Promotions($this->promotions)->redeem($order);

        self::assertSame([['1', 'gone']], array_map(static fn (PromotionRedemption $redemption): array => [$redemption->orderNumber, $redemption->promotion], $redeemed));
        self::assertNull($this->promotions->find('gone'));
    }

    /**
     * Another order took the last use of the code meanwhile: the placement is refused, and nothing is counted.
     */
    public function testAUseTakenMeanwhileRefusesThePlacement(): void
    {
        $coded = Promotion::percentOff('coded', 'Coded', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $this->promotions->add($coded);
        $code = PromotionCode::of('ONCE', $coded, 1);
        $this->promotions->addCode($code);
        $order = $this->order(['PDF' => 1], ['ONCE']);
        $order->place(new DateTimeImmutable('2026-11-28T12:00:00Z'));

        $code->claim();

        try {
            new Promotions($this->promotions)->redeem($order);
            self::fail('A use beyond the limit was counted.');
        } catch (PromotionRefused $promotionRefused) {
            self::assertSame('The promotion coded or its code ONCE reached its limit of uses before this order was counted.', $promotionRefused->getMessage());
        }

        self::assertSame([0, 1], [$coded->uses(), $code->uses()]);
        self::assertNull($this->promotions->findRedemption('1', 'coded'));
    }

    public function testAPromotionTakenToItsLimitMeanwhileRefusesThePlacement(): void
    {
        $once = Promotion::percentOff('once', 'Once', 100_000, Currency::of('CAD'))->when(usageLimit: 1);
        $this->promotions->add($once);
        $order = $this->order(['PDF' => 1]);
        $order->place(new DateTimeImmutable('2026-11-28T12:00:00Z'));

        $once->claim();

        $this->expectExceptionObject(PromotionRefused::exhausted('once', null));
        $this->expectExceptionMessageIsOrContains('The promotion once reached its limit of uses before this order was counted.');

        new Promotions($this->promotions)->redeem($order);
    }

    /**
     * A buyer types the code from a flyer in lowercase: it unlocks the promotion all the same.
     */
    public function testACodeIsMatchedWhateverItsCase(): void
    {
        $coded = Promotion::percentOff('coded', 'Coded', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $this->promotions->add($coded);
        $this->promotions->addCode(PromotionCode::of('WELCOME-7KQ4M2XA', $coded, 1));

        $order = $this->order(['PDF' => 1], ['welcome-7kq4m2xa']);

        self::assertSame([['product:PDF', 'Coded', -100]], $this->discounts($order));
        self::assertSame('promotion:coded:WELCOME-7KQ4M2XA', $order->adjustments(AdjustmentType::Promotion)[0]->origin);
    }

    /**
     * A promotion and a code both at their longest still make an origin the adjustment keeps.
     */
    public function testTheLongestPromotionAndCodeMakeAnOrigin(): void
    {
        $name = str_repeat('p', 64);
        $entered = str_repeat('C', 64);
        $coded = Promotion::percentOff($name, 'Coded', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $this->promotions->add($coded);
        $this->promotions->addCode(PromotionCode::of($entered, $coded));

        $order = $this->order(['PDF' => 1], [$entered]);

        self::assertSame([['product:PDF', 'Coded', -100]], $this->discounts($order));
        self::assertSame(['promotion' => $name, 'code' => $entered], PromotionEvaluator::originOf($order->adjustments(AdjustmentType::Promotion)[0]));
    }

    /**
     * Each code entered is looked up once per evaluation, whatever the number of promotions asking for a code.
     */
    public function testEachEnteredCodeIsLookedUpOnce(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            $this->promotions->add(Promotion::percentOff($name, $name, 1, Currency::of('CAD'))->when(requiresCode: true));
        }

        $order = Order::open('9', Currency::of('CAD'));
        $order->enterCode('NOPE');
        $order->enterCode('nope');
        $order->enterCode('OTHER');
        new PromotionEvaluator($this->promotions)->evaluate($order, $this->clock->now);

        self::assertSame(['NOPE' => 1, 'OTHER' => 1], $this->promotions->lookups);
    }

    public function testAnOriginNamesThePromotionAndItsCode(): void
    {
        $cad = Money::of(-1, Currency::of('CAD'));

        self::assertSame(['promotion' => 'sale', 'code' => null], PromotionEvaluator::originOf(Adjustment::of(AdjustmentType::Promotion, 'Sale', $cad, origin: 'promotion:sale')));
        self::assertSame(['promotion' => 'sale', 'code' => 'SAVE-10'], PromotionEvaluator::originOf(Adjustment::of(AdjustmentType::Promotion, 'Sale', $cad, origin: 'promotion:sale:SAVE-10')));
        self::assertNull(PromotionEvaluator::originOf(Adjustment::of(AdjustmentType::Promotion, 'Sale', $cad)));
        self::assertNull(PromotionEvaluator::originOf(Adjustment::of(AdjustmentType::Promotion, 'Sale', $cad, origin: 'loyalty:gold')));
    }

    /**
     * Whatever follows the promotion in an origin is its code, colons included.
     */
    public function testAnOriginKeepsItsCodeWhole(): void
    {
        $cad = Money::of(-1, Currency::of('CAD'));

        self::assertSame(['promotion' => 'sale', 'code' => 'SAVE:10'], PromotionEvaluator::originOf(Adjustment::of(AdjustmentType::Promotion, 'Sale', $cad, origin: 'promotion:sale:SAVE:10')));
    }

    /**
     * Two codes entered, each unlocking its own promotion: both apply, and each is returned with its promotion.
     */
    public function testEachEnteredCodeUnlocksItsPromotion(): void
    {
        $first = Promotion::percentOff('first', 'First', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $second = Promotion::percentOff('second', 'Second', 100_000, Currency::of('CAD'))->when(requiresCode: true);
        $this->promotions->add($first);
        $this->promotions->add($second);
        $this->promotions->addCode(PromotionCode::of('ONE', $first));
        $this->promotions->addCode(PromotionCode::of('TWO', $second));

        $order = Order::open('9', Currency::of('CAD'));
        $cad = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'PDF'), 'PDF', $cad, 'goods'), 1, $cad);
        $order->enterCode('ONE');
        $order->enterCode('TWO');

        $applied = new PromotionEvaluator($this->promotions)->evaluate($order, $this->clock->now);

        self::assertSame([[$first, 'ONE', -100], [$second, 'TWO', -90]], array_map(static fn (array $one): array => [$one['promotion'], $one['code']?->code, $one['adjustments'][0]->amount->minorUnits], $applied));
    }

    /**
     * A promotion counted for the order before is skipped, and the next ones are still counted.
     */
    public function testAPromotionCountedBeforeDoesNotStopTheNextOnes(): void
    {
        $first = Promotion::percentOff('first', 'First', 100_000, Currency::of('CAD'));
        $second = Promotion::percentOff('second', 'Second', 100_000, Currency::of('CAD'));
        $this->promotions->add($first);
        $this->promotions->add($second);

        $order = $this->order(['PDF' => 1]);
        $order->place(new DateTimeImmutable('2026-11-28T12:00:00Z'));

        $this->promotions->addRedemption(PromotionRedemption::of('1', 'first', null));

        $redeemed = new Promotions($this->promotions)->redeem($order);

        self::assertSame(['second'], array_map(static fn (PromotionRedemption $redemption): string => $redemption->promotion, $redeemed));
        self::assertSame([0, 1], [$first->uses(), $second->uses()]);
    }

    public function testACartIsNotRedeemed(): void
    {
        $this->expectException(PromotionRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 1 is not placed: its promotions are counted once it is.');

        new Promotions($this->promotions)->redeem($this->order(['PDF' => 1]));
    }

    /**
     * @param array<string, int> $lines SKU → quantity
     * @param list<string>       $codes
     */
    private function order(array $lines, array $codes = [], ?string $channel = null, ?string $group = null): Order
    {
        $cad = static fn (int $minorUnits): Money => Money::of($minorUnits, Currency::of('CAD'));
        $modifier = new OrderModifier(
            [new ProductSellables(new InMemoryProducts(
                Product::create('DICE', ProductKind::Physical, 'Dice', $cad(2_000), 'goods'),
                Product::create('PDF', ProductKind::Digital, 'PDF', $cad(1_000), 'goods'),
                Product::create('D6', ProductKind::Physical, 'D6', $cad(1_000), 'goods'),
                Product::create('VAULT', ProductKind::Physical, 'Vault', $cad(1_000_000_000_000), 'exempt'),
            ))],
            [],
            [new PromotionProcessor(new PromotionEvaluator($this->promotions), $this->clock), new TaxOrderProcessor(new ConfiguredTaxRates(['goods' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]))],
        );
        $order = Order::open('1', Currency::of('CAD'));
        $context = PricingContext::of(1, $this->clock->now);
        $modifier->changeAudience($order, $channel, $group, $context);

        foreach ($lines as $sku => $quantity) {
            $modifier->add($order, SellableReference::of('product', $sku), $quantity, $context);
        }

        foreach ($codes as $code) {
            $modifier->enterCode($order, $code);
        }

        $modifier->changeTaxZone($order, 'QC');

        return $order;
    }

    /**
     * @return list<array{string, string, int}>
     */
    private function discounts(Order $order): array
    {
        return array_map(static fn (Adjustment $adjustment): array => [$adjustment->line?->value() ?? '', $adjustment->label, $adjustment->amount->minorUnits], $order->adjustments(AdjustmentType::Promotion));
    }
}
