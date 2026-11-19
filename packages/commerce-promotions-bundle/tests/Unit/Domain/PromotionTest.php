<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Promotion\PromotionAction;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Promotion::class)]
#[CoversClass(PromotionCode::class)]
final class PromotionTest extends TestCase
{
    public function testEachActionHoldsWhatItGives(): void
    {
        $percent = Promotion::percentOff('sale', 'Sale −10 %', 100_000, Currency::of('CAD'));
        self::assertSame(PromotionAction::PercentOff, $percent->action);
        self::assertSame(100_000, $percent->partsPerMillion);
        self::assertTrue($percent->amount()->isZero());
        self::assertSame([0, 0], [$percent->buyQuantity, $percent->payQuantity]);
        self::assertSame('CAD', $percent->currency->code);
        self::assertSame(1_000_000, Promotion::percentOff('free', 'Free', 1_000_000, Currency::of('CAD'))->partsPerMillion);
        self::assertSame(1, Promotion::percentOff('tiny', 'Tiny', 1, Currency::of('CAD'))->partsPerMillion);

        $amount = Promotion::amountOff('five', '5 $ off', Money::of(500, Currency::of('CAD')));
        self::assertSame(PromotionAction::AmountOff, $amount->action);
        self::assertSame(500, $amount->amount()->minorUnits);
        self::assertSame(0, $amount->partsPerMillion);

        $bundle = Promotion::buyNPayX('three-for-two', '3 for 2', 3, 2, Currency::of('CAD'));
        self::assertSame(PromotionAction::BuyNPayX, $bundle->action);
        self::assertSame(3, $bundle->buyQuantity);
        self::assertSame(2, $bundle->payQuantity);
        self::assertSame(0, Promotion::buyNPayX('free-one', 'Free', 1, 0, Currency::of('CAD'))->payQuantity);
        self::assertSame(1_000, Promotion::buyNPayX('bulk', 'Bulk', 1_000, 999, Currency::of('CAD'))->buyQuantity);
    }

    public function testAPromotionStartsWithoutConditions(): void
    {
        $promotion = Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD'));

        self::assertSame('sale', $promotion->code);
        self::assertSame('Sale', $promotion->label());
        self::assertSame([], $promotion->after);
        self::assertFalse($promotion->exclusive);
        self::assertSame([], $promotion->skus);
        self::assertSame([], $promotion->channels);
        self::assertSame([], $promotion->customerGroups);
        self::assertFalse($promotion->requiresCode);
        self::assertNull($promotion->startsAt);
        self::assertNull($promotion->endsAt);
        self::assertNull($promotion->usageLimit);
        self::assertTrue($promotion->minimumSubtotal()->isZero());
        self::assertSame(0, $promotion->uses());
        self::assertTrue($promotion->isActive());
        self::assertTrue($promotion->coversSku('ANY'));
        self::assertTrue($promotion->appliesTo(null, null, Currency::of('CAD'), new DateTimeImmutable()));
    }

    public function testConditionsAreSetAndKept(): void
    {
        $starts = new DateTimeImmutable('2026-11-27T00:00:00Z');
        $ends = new DateTimeImmutable('2026-12-01T00:00:00Z');
        $promotion = Promotion::percentOff('bf', 'BF', 200_000, Currency::of('CAD'))->when(
            skus: ['DICE', 'DICE', 'PDF'],
            channels: ['web', 'web', 'app'],
            customerGroups: ['vip', 'vip', 'staff'],
            requiresCode: true,
            startsAt: $starts,
            endsAt: $ends,
            usageLimit: 2,
            minimumSubtotal: Money::of(5_000, Currency::of('CAD')),
            after: ['early'],
            exclusive: true,
        );

        self::assertSame(['DICE', 'PDF'], $promotion->skus);
        self::assertSame(['web', 'app'], $promotion->channels);
        self::assertSame(['vip', 'staff'], $promotion->customerGroups);
        self::assertTrue($promotion->requiresCode);
        self::assertSame($starts, $promotion->startsAt);
        self::assertSame($ends, $promotion->endsAt);
        self::assertSame(2, $promotion->usageLimit);
        self::assertSame(5_000, $promotion->minimumSubtotal()->minorUnits);
        self::assertSame(['early'], $promotion->after);
        self::assertTrue($promotion->exclusive);
        self::assertSame(200_000, $promotion->partsPerMillion);
        self::assertTrue($promotion->coversSku('PDF'));
        self::assertFalse($promotion->coversSku('MEEPLE'));

        $kept = $promotion->when(after: ['first']);
        self::assertSame(['DICE', 'PDF'], $kept->skus);
        self::assertSame(['first'], $kept->after);
        self::assertTrue($kept->exclusive);
        self::assertSame(2, $kept->usageLimit);
        self::assertSame($ends, $kept->endsAt);
        self::assertSame(5_000, $kept->minimumSubtotal()->minorUnits);

        $changed = $promotion->when(startsAt: new DateTimeImmutable('2026-11-20T00:00:00Z'), endsAt: new DateTimeImmutable('2026-11-30T00:00:00Z'), usageLimit: 5);
        self::assertSame('2026-11-20', $changed->startsAt?->format('Y-m-d'));
        self::assertSame('2026-11-30', $changed->endsAt?->format('Y-m-d'));
        self::assertSame(5, $changed->usageLimit);

        $since = Promotion::percentOff('since', 'Since', 1, Currency::of('CAD'))->when(startsAt: new DateTimeImmutable('2026-01-01'));
        $until = Promotion::percentOff('until', 'Until', 1, Currency::of('CAD'))->when(endsAt: new DateTimeImmutable('2026-01-01'));
        self::assertNull($since->endsAt);
        self::assertNull($until->startsAt);

        $amount = Promotion::amountOff('five', 'Five', Money::of(500, Currency::of('CAD')))->when(after: ['x']);
        self::assertSame(500, $amount->amount()->minorUnits);
        $bundle = Promotion::buyNPayX('b', 'B', 4, 3, Currency::of('CAD'))->when(after: ['x']);
        self::assertSame([4, 3], [$bundle->buyQuantity, $bundle->payQuantity]);
    }

    /**
     * @return iterable<string, array{?string, ?string, string, string, bool}>
     */
    public static function applications(): iterable
    {
        yield 'everything holds' => ['web', 'vip', 'CAD', '2026-11-28T00:00:00Z', true];
        yield 'at the start' => ['web', 'vip', 'CAD', '2026-11-27T00:00:00Z', true];
        yield 'at the end, excluded' => ['web', 'vip', 'CAD', '2026-12-01T00:00:00Z', false];
        yield 'before' => ['web', 'vip', 'CAD', '2026-11-26T00:00:00Z', false];
        yield 'another channel' => ['store', 'vip', 'CAD', '2026-11-28T00:00:00Z', false];
        yield 'no channel' => [null, 'vip', 'CAD', '2026-11-28T00:00:00Z', false];
        yield 'another group' => ['web', 'retail', 'CAD', '2026-11-28T00:00:00Z', false];
        yield 'no group' => ['web', null, 'CAD', '2026-11-28T00:00:00Z', false];
        yield 'another currency' => ['web', 'vip', 'USD', '2026-11-28T00:00:00Z', false];
    }

    #[DataProvider('applications')]
    public function testAPromotionAppliesWhereToWhomAndWhenItSays(?string $channel, ?string $group, string $currency, string $at, bool $applies): void
    {
        $promotion = Promotion::percentOff('bf', 'BF', 100_000, Currency::of('CAD'))->when(channels: ['web', 'app'], customerGroups: ['vip', 'staff'], startsAt: new DateTimeImmutable('2026-11-27T00:00:00Z'), endsAt: new DateTimeImmutable('2026-12-01T00:00:00Z'));

        self::assertSame($applies, $promotion->appliesTo($channel, $group, Currency::of($currency), new DateTimeImmutable($at)));
    }

    public function testUsesAreCountedUpToTheLimit(): void
    {
        $promotion = Promotion::percentOff('once', 'Once', 100_000, Currency::of('CAD'))->when(usageLimit: 2);
        self::assertTrue($promotion->claim());
        self::assertSame(1, $promotion->uses());
        self::assertTrue($promotion->appliesTo(null, null, Currency::of('CAD'), new DateTimeImmutable()));

        self::assertTrue($promotion->claim());
        self::assertFalse($promotion->appliesTo(null, null, Currency::of('CAD'), new DateTimeImmutable()));
        self::assertFalse($promotion->claim());
        self::assertSame(2, $promotion->uses());

        $promotion->release();
        self::assertSame(1, $promotion->uses());

        $open = Promotion::percentOff('open', 'Open', 1, Currency::of('CAD'));
        $open->release();
        self::assertSame(0, $open->uses());
        self::assertTrue($open->claim());
    }

    public function testAWithdrawnPromotionAppliesToNothing(): void
    {
        $promotion = Promotion::percentOff('sale', 'Sale', 100_000, Currency::of('CAD'));
        $promotion->withdraw();
        self::assertFalse($promotion->isActive());
        self::assertFalse($promotion->appliesTo(null, null, Currency::of('CAD'), new DateTimeImmutable()));

        $promotion->restore();
        self::assertTrue($promotion->appliesTo(null, null, Currency::of('CAD'), new DateTimeImmutable()));
    }

    /**
     * @return iterable<string, array{callable(): Promotion, string}>
     */
    public static function invalidPromotions(): iterable
    {
        $cad = Currency::of('CAD');

        yield 'no share' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 0, $cad), 'A share off lies between 1 and 1000000 parts per million; 0 does not.'];
        yield 'more than everything' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1_000_001, $cad), 'A share off lies between 1 and 1000000 parts per million; 1000001 does not.'];
        yield 'no amount' => [static fn (): Promotion => Promotion::amountOff('p', 'P', Money::zero($cad)), 'The promotion p takes an amount above zero off.'];
        yield 'as many as paid' => [static fn (): Promotion => Promotion::buyNPayX('p', 'P', 2, 2, $cad), 'A promotion gives more units than it charges, up to 1000: 2 for 2 does not.'];
        yield 'paying a negative' => [static fn (): Promotion => Promotion::buyNPayX('p', 'P', 2, -1, $cad), 'A promotion gives more units than it charges'];
        yield 'too many' => [static fn (): Promotion => Promotion::buyNPayX('p', 'P', 1_001, 1, $cad), 'A promotion gives more units than it charges'];
        yield 'uppercase code' => [static fn (): Promotion => Promotion::percentOff('Sale', 'P', 1, $cad), '"Sale" is not a promotion code: up to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'code too long' => [static fn (): Promotion => Promotion::percentOff(str_repeat('a', 65), 'P', 1, $cad), 'is not a promotion code'];
        yield 'code with a trailing newline' => [static fn (): Promotion => Promotion::percentOff("sale\n", 'P', 1, $cad), 'is not a promotion code'];
        yield 'blank label' => [static fn (): Promotion => Promotion::percentOff('p', ' ', 1, $cad), 'The promotion p has a label of 1 to 255 characters, which the receipt shows.'];
        yield 'label too long' => [static fn (): Promotion => Promotion::percentOff('p', str_repeat('é', 256), 1, $cad), 'The promotion p has a label of 1 to 255 characters'];
        yield 'SKU with a space' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(skus: ['A B']), '"A B" is not a SKU, a channel, a customer group, a delivery method or a promotion: 1 to 64 visible ASCII characters.'];
        yield 'empty channel' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(channels: ['']), 'is not a SKU, a channel, a customer group, a delivery method or a promotion'];
        yield 'group too long' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(customerGroups: [str_repeat('g', 65)]), 'is not a SKU, a channel, a customer group, a delivery method or a promotion'];
        yield 'group with a trailing newline' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(customerGroups: ["vip\n"]), 'is not a SKU, a channel, a customer group, a delivery method or a promotion'];
        yield 'no use' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(usageLimit: 0), 'The promotion p has a minimum of zero or more and limits of one use or more.'];
        yield 'negative minimum' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(minimumSubtotal: Money::of(-1, $cad)), 'The promotion p has a minimum of zero or more'];
        yield 'minimum in another currency' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(minimumSubtotal: Money::of(1, Currency::of('USD'))), 'The minimum of the promotion p counts in CAD.'];
        yield 'ending at its start' => [static fn (): Promotion => Promotion::percentOff('p', 'P', 1, $cad)->when(startsAt: new DateTimeImmutable('2026-01-01'), endsAt: new DateTimeImmutable('2026-01-01')), 'The promotion p ends after it starts.'];
    }

    /**
     * @param callable(): Promotion $make
     */
    #[DataProvider('invalidPromotions')]
    public function testAnInvalidPromotionIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheBoundsAreAccepted(): void
    {
        $promotion = Promotion::percentOff(str_repeat('a', 64), str_repeat('é', 255), 1, Currency::of('CAD'))->when(skus: [str_repeat('S', 64)], usageLimit: 1, minimumSubtotal: Money::zero(Currency::of('CAD')));

        self::assertSame(64, \strlen($promotion->code));
        self::assertSame(1, $promotion->usageLimit);
    }

    public function testACodeIsCountedUpToItsLimit(): void
    {
        $promotion = Promotion::percentOff('sale', 'Sale', 1, Currency::of('CAD'));
        $code = PromotionCode::of('SAVE-10', $promotion, 2);

        self::assertSame('SAVE-10', $code->code);
        self::assertSame('sale', $code->promotion);
        self::assertSame(2, $code->usageLimit);
        self::assertSame(0, $code->uses());
        self::assertTrue($code->isUsable());

        self::assertTrue($code->claim());
        self::assertTrue($code->isUsable());
        self::assertTrue($code->claim());
        self::assertSame(2, $code->uses());
        self::assertFalse($code->isUsable());
        self::assertFalse($code->claim());
        self::assertSame(2, $code->uses());

        $open = PromotionCode::of('ABC', $promotion);
        self::assertTrue($open->claim());
        self::assertNull($open->usageLimit);
        self::assertTrue($open->isUsable());
        self::assertSame(64, \strlen(PromotionCode::of(str_repeat('A', 64), $promotion, 1)->code));
    }

    /**
     * @return iterable<string, array{string, ?int, string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'too short' => ['AB', null, '"AB" is not a promotion code: 3 to 64 uppercase letters, digits, "_" or "-".'];
        yield 'lowercase' => ['save10', null, 'is not a promotion code'];
        yield 'too long' => [str_repeat('A', 65), null, 'is not a promotion code'];
        yield 'starting with a dash' => ['-SAVE', null, 'is not a promotion code'];
        yield 'trailing newline' => ["SAVE\n", null, 'is not a promotion code'];
        yield 'no use' => ['SAVE', 0, 'The code SAVE allows one use or more.'];
    }

    #[DataProvider('invalidCodes')]
    public function testAnInvalidCodeIsRefused(string $code, ?int $limit, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PromotionCode::of($code, Promotion::percentOff('sale', 'Sale', 1, Currency::of('CAD')), $limit);
    }
}
