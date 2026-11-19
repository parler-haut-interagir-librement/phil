<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Domain;

use Phil\CommercePromotionsBundle\Domain\Exception\PromotionRefused;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCustomerUse;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionTranslation;
use Phil\CommercePromotionsBundle\Domain\Promotion\PromotionAction;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Bundles, free shipping, exclusions, uses per customer, owned products, ordering and translations of a promotion.
 */
#[CoversClass(Promotion::class)]
#[CoversClass(BundleGroup::class)]
#[CoversClass(PromotionTranslation::class)]
#[CoversClass(PromotionCustomerUse::class)]
#[CoversClass(PromotionRedemption::class)]
#[CoversClass(PromotionRefused::class)]
final class PromotionConditionsTest extends TestCase
{
    public function testABundleHoldsItsGroupsAndItsPrice(): void
    {
        $bundle = Promotion::bundlePrice('starter', 'Starter box', [BundleGroup::of(['BOOK']), BundleGroup::of(['D6', 'D6', 'D20'], 2)], Money::of(5_000, Currency::of('CAD')));

        self::assertSame(PromotionAction::BundlePrice, $bundle->action);
        self::assertSame(5_000, $bundle->amount()->minorUnits);
        self::assertSame([['BOOK'], ['D6', 'D20']], array_map(static fn (BundleGroup $group): array => $group->skus, $bundle->groups()));
        self::assertSame([1, 2], array_map(static fn (BundleGroup $group): int => $group->quantity, $bundle->groups()));
        self::assertSame(['skus' => ['D6', 'D20'], 'quantity' => 2], $bundle->groups()[1]->toArray());
        self::assertTrue($bundle->groups()[1]->covers('D20'));
        self::assertFalse($bundle->groups()[1]->covers('BOOK'));
        self::assertSame([], Promotion::percentOff('p', 'P', 1, Currency::of('CAD'))->groups());

        $kept = $bundle->when(channels: ['web']);
        self::assertCount(2, $kept->groups());
        self::assertTrue(Promotion::bundlePrice('free', 'Free', [BundleGroup::of(['A'])], Money::zero(Currency::of('CAD')))->amount()->isZero());
        self::assertCount(10, Promotion::bundlePrice('ten', 'Ten', array_map(static fn (int $n): BundleGroup => BundleGroup::of(['S'.$n]), range(1, 10)), Money::zero(Currency::of('CAD')))->groups());
    }

    public function testAGroupTakesItsBounds(): void
    {
        self::assertSame(1_000, BundleGroup::of(['A'], 1_000)->quantity);
        self::assertCount(100, BundleGroup::of(array_map(static fn (int $n): string => 'S'.$n, range(1, 100)))->skus);
        self::assertSame(['A'], BundleGroup::fromArray(['skus' => ['A'], 'quantity' => 3])->skus);
        self::assertSame(3, BundleGroup::fromArray(['skus' => ['A'], 'quantity' => 3])->quantity);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidBundles(): iterable
    {
        $cad = Currency::of('CAD');

        yield 'no group' => [static fn (): Promotion => Promotion::bundlePrice('b', 'B', [], Money::zero($cad)), 'A bundle holds 1 to 10 groups; 0 is not.'];
        yield 'eleven groups' => [static fn (): Promotion => Promotion::bundlePrice('b', 'B', array_map(static fn (int $n): BundleGroup => BundleGroup::of(['S'.$n]), range(1, 11)), Money::zero($cad)), 'A bundle holds 1 to 10 groups; 11 is not.'];
        yield 'a product in two groups' => [static fn (): Promotion => Promotion::bundlePrice('b', 'B', [BundleGroup::of(['A', 'B']), BundleGroup::of(['B'])], Money::zero($cad)), 'The product B belongs to two groups of the bundle b.'];
        yield 'a negative price' => [static fn (): Promotion => Promotion::bundlePrice('b', 'B', [BundleGroup::of(['A'])], Money::of(-1, $cad)), 'The bundle b has a price of zero or more.'];
        yield 'an empty group' => [static fn (): BundleGroup => BundleGroup::of([]), 'A bundle group lists 1 to 100 products; 0 is not.'];
        yield 'a crowded group' => [static fn (): BundleGroup => BundleGroup::of(array_map(static fn (int $n): string => 'S'.$n, range(1, 101))), 'A bundle group lists 1 to 100 products; 101 is not.'];
        yield 'a SKU with a space' => [static fn (): BundleGroup => BundleGroup::of(['A B']), '"A B" is not a SKU: 1 to 64 visible ASCII characters.'];
        yield 'a SKU with a newline' => [static fn (): BundleGroup => BundleGroup::of(["A\n"]), 'is not a SKU'];
        yield 'a SKU too long' => [static fn (): BundleGroup => BundleGroup::of([str_repeat('S', 65)]), 'is not a SKU'];
        yield 'no unit' => [static fn (): BundleGroup => BundleGroup::of(['A'], 0), 'A bundle group asks for 1 to 1000 units; 0 is not.'];
        yield 'too many units' => [static fn (): BundleGroup => BundleGroup::of(['A'], 1_001), 'A bundle group asks for 1 to 1000 units; 1001 is not.'];
    }

    /**
     * @param callable(): mixed $make
     */
    #[DataProvider('invalidBundles')]
    public function testAnInvalidBundleIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAFreeShippingWaivesItsMethods(): void
    {
        $everywhere = Promotion::freeShipping('ship', 'Free shipping', Currency::of('CAD'));
        self::assertSame(PromotionAction::FreeShipping, $everywhere->action);
        self::assertTrue($everywhere->amount()->isZero());
        self::assertTrue($everywhere->waives('post'));
        self::assertFalse($everywhere->waives(null));

        $post = $everywhere->when(deliveryMethods: ['post', 'post', 'courier']);
        self::assertSame(['post', 'courier'], $post->deliveryMethods);
        self::assertTrue($post->waives('courier'));
        self::assertFalse($post->waives('pickup'));
        self::assertSame(['post', 'courier'], $post->when(skus: ['A'])->deliveryMethods);
    }

    public function testExcludedProductsAreNeverDiscounted(): void
    {
        $promotion = Promotion::percentOff('sale', 'Sale', 1, Currency::of('CAD'))->when(excludedSkus: ['GIFT', 'GIFT']);

        self::assertSame(['GIFT'], $promotion->excludedSkus);
        self::assertTrue($promotion->coversSku('DICE'));
        self::assertFalse($promotion->coversSku('GIFT'));

        $listed = $promotion->when(skus: ['GIFT', 'DICE']);
        self::assertSame(['GIFT'], $listed->excludedSkus);
        self::assertFalse($listed->coversSku('GIFT'));
        self::assertTrue($listed->coversSku('DICE'));
        self::assertFalse($listed->coversSku('PDF'));
    }

    public function testTheCustomerConditionsAreKept(): void
    {
        $promotion = Promotion::percentOff('loyal', 'Loyal', 1, Currency::of('CAD'))->when(ownedSkus: ['CORE', 'CORE'], perCustomerLimit: 2);

        self::assertSame(2, $promotion->perCustomerLimit);
        self::assertSame(['CORE'], $promotion->ownedSkus);
        self::assertSame(2, $promotion->when(channels: ['web'])->perCustomerLimit);
        self::assertSame(['CORE'], $promotion->when(channels: ['web'])->ownedSkus);
        self::assertNull(Promotion::percentOff('p', 'P', 1, Currency::of('CAD'))->perCustomerLimit);
        self::assertSame(1, Promotion::percentOff('p', 'P', 1, Currency::of('CAD'))->when(perCustomerLimit: 1)->perCustomerLimit);
        self::assertSame(3, $promotion->when(perCustomerLimit: 3)->perCustomerLimit);
    }

    public function testIncompatibilityGoesBothWays(): void
    {
        $cad = Currency::of('CAD');
        $sale = Promotion::percentOff('sale', 'Sale', 1, $cad)->when(incompatibleWith: ['clearance', 'clearance']);
        $clearance = Promotion::percentOff('clearance', 'Clearance', 1, $cad);
        $other = Promotion::percentOff('other', 'Other', 1, $cad);

        self::assertSame(['clearance'], $sale->incompatibleWith);
        self::assertTrue($sale->isIncompatibleWith($clearance));
        self::assertTrue($clearance->isIncompatibleWith($sale));
        self::assertFalse($sale->isIncompatibleWith($other));
        self::assertFalse($other->isIncompatibleWith($clearance));
        self::assertSame(['clearance'], $sale->when(skus: ['A'])->incompatibleWith);
        self::assertSame(['early'], $sale->when(after: ['early', 'early'])->after);
        self::assertSame(['early'], $sale->when(after: ['early'])->when(skus: ['A'])->after);
    }

    /**
     * @return iterable<string, array{callable(): Promotion, string}>
     */
    public static function invalidConditions(): iterable
    {
        $cad = Currency::of('CAD');

        yield 'after itself' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(after: ['p']), 'The promotion p is never ordered after itself, nor incompatible with itself.'];
        yield 'incompatible with itself' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(incompatibleWith: ['p']), 'The promotion p is never ordered after itself'];
        yield 'no use per customer' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(perCustomerLimit: 0), 'The promotion p has a minimum of zero or more and limits of one use or more.'];
        yield 'a list too long' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(excludedSkus: array_map(static fn (int $n): string => 'S'.$n, range(1, 501))), 'A condition of the promotion p lists up to 500 names.'];
        yield 'a bad delivery method' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(deliveryMethods: ['by post']), '"by post" is not a SKU'];
        yield 'a bad owned product' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(ownedSkus: ['']), 'is not a SKU'];
        yield 'a bad excluded product' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(excludedSkus: ["A\n"]), 'is not a SKU'];
        yield 'a bad incompatible promotion' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(incompatibleWith: ['a b']), 'is not a SKU'];
        yield 'a bad earlier promotion' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(after: ['a b']), 'is not a SKU'];
    }

    /**
     * @param callable(): Promotion $make
     */
    #[DataProvider('invalidConditions')]
    public function testAnInvalidConditionIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAListTakesFiveHundredNames(): void
    {
        self::assertCount(500, Promotion::percentOff('p', 'P', 1, Currency::of('CAD'))->when(skus: array_map(static fn (int $n): string => 'S'.$n, range(1, 500)))->skus);
    }

    public function testALabelIsRewrittenAndTranslated(): void
    {
        $promotion = Promotion::percentOff('bf', 'Black Friday', 1, Currency::of('CAD'));
        $promotion->relabel('Black Friday −20 %');
        self::assertSame('Black Friday −20 %', $promotion->label());
        self::assertSame('Black Friday −20 %', $promotion->when(skus: ['A'])->label());

        self::assertSame(PromotionTranslation::class, Promotion::translationClass());
        self::assertSame(['label'], Promotion::translatedFields());

        $translation = $promotion->newTranslation(Locale::fromString('fr_CA'));
        self::assertSame($promotion, $translation->translatable());
        self::assertSame('fr_CA', $translation->locale()->value);
        self::assertSame('', $translation->label());

        $translation->relabel('Vendredi fou −20 %');
        self::assertSame('Vendredi fou −20 %', $translation->label());
        $translation->relabel(str_repeat('é', 255));
        self::assertSame(255, mb_strlen($translation->label()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLabels(): iterable
    {
        yield 'blank' => [' '];
        yield 'too long' => [str_repeat('é', 256)];
    }

    #[DataProvider('invalidLabels')]
    public function testAnInvalidLabelIsRefused(string $label): void
    {
        $promotion = Promotion::percentOff('bf', 'Black Friday', 1, Currency::of('CAD'));

        try {
            $promotion->relabel($label);
            self::fail('The label was taken.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('The promotion bf has a label of 1 to 255 characters, which the receipt shows.', $invalidValue->getMessage());
        }

        self::assertSame('Black Friday', $promotion->label());
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The promotion bf has a label of 1 to 255 characters in fr.');

        $promotion->newTranslation(Locale::fromString('fr'))->relabel($label);
    }

    public function testACustomerIsCountedUpToTheLimit(): void
    {
        $uses = PromotionCustomerUse::of('loyal', 'customer-7');

        self::assertSame(['loyal', 'customer-7', 0], [$uses->promotion, $uses->customer, $uses->uses()]);
        self::assertTrue($uses->claim(2));
        self::assertTrue($uses->claim(2));
        self::assertFalse($uses->claim(2));
        self::assertSame(2, $uses->uses());
        self::assertTrue($uses->claim(3));
        self::assertSame(3, $uses->uses());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCustomerUses(): iterable
    {
        yield 'a promotion with a space' => ['a b', 'c'];
        yield 'a customer with a newline' => ['a', "c\n"];
        yield 'an empty customer' => ['a', ''];
        yield 'a customer too long' => ['a', str_repeat('c', 65)];
    }

    #[DataProvider('invalidCustomerUses')]
    public function testAnInvalidCustomerUseIsRefused(string $promotion, string $customer): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A promotion and a customer are 1 to 64 visible ASCII characters.');

        PromotionCustomerUse::of($promotion, $customer);
    }

    public function testARedemptionKeepsTheCustomer(): void
    {
        $redemption = PromotionRedemption::of('A-1', 'loyal', 'CODE', 'customer-7');
        self::assertSame(['A-1', 'loyal', 'CODE', 'customer-7'], [$redemption->orderNumber, $redemption->promotion, $redemption->code, $redemption->customer]);

        $guest = PromotionRedemption::of('A-2', 'loyal', null);
        self::assertNull($guest->code);
        self::assertNull($guest->customer);
        self::assertSame(str_repeat('c', 64), PromotionRedemption::of('A-3', 'loyal', null, str_repeat('c', 64))->customer);
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string}>
     */
    public static function invalidRedemptions(): iterable
    {
        yield 'an order with a space' => ['A 1', 'p', null, null];
        yield 'a promotion too long' => ['A', str_repeat('p', 65), null, null];
        yield 'an empty code' => ['A', 'p', '', null];
        yield 'a customer with a newline' => ['A', 'p', null, "c\n"];
    }

    #[DataProvider('invalidRedemptions')]
    public function testAnInvalidRedemptionIsRefused(string $order, string $promotion, ?string $code, ?string $customer): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('An order number, a promotion, a code and a customer are 1 to 64 visible ASCII characters.');

        PromotionRedemption::of($order, $promotion, $code, $customer);
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame('A batch holds 1 to 10000 codes; 0 does not.', PromotionRefused::batch(0)->getMessage());
        self::assertSame('The promotion p reached its limit of uses before this order was counted.', PromotionRefused::exhausted('p', null)->getMessage());
        self::assertSame('The promotion p or its code C reached its limit of uses before this order was counted.', PromotionRefused::exhausted('p', 'C')->getMessage());
        self::assertSame('The customer reached the limit of uses of the promotion p before this order was counted.', PromotionRefused::exhaustedForCustomer('p')->getMessage());
        self::assertSame('The order 7 is not placed: its promotions are counted once it is.', PromotionRefused::notPlaced('7')->getMessage());
    }
}
