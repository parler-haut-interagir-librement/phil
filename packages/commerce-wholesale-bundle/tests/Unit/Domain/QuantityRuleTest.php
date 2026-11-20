<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Domain;

use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Domain\Quantity\QuantityBreach;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuantityRule::class)]
#[CoversClass(QuantityBreach::class)]
final class QuantityRuleTest extends TestCase
{
    public function testARuleForEveryGroupOrForOne(): void
    {
        $every = QuantityRule::of('DICE-D20');
        $wholesale = QuantityRule::of('DICE-D20', 'wholesale', 6, 60, 6);

        self::assertSame(['DICE-D20', null, 1, null, 1], [$every->sku, $every->customerGroup, $every->minimum(), $every->maximum(), $every->increment()]);
        self::assertSame(['wholesale', 6, 60, 6], [$wholesale->customerGroup, $wholesale->minimum(), $wholesale->maximum(), $wholesale->increment()]);
        self::assertSame('', QuantityRule::EVERY_GROUP);
    }

    /**
     * @return iterable<string, array{int, ?QuantityBreach}>
     */
    public static function quantities(): iterable
    {
        yield 'under the minimum' => [5, QuantityBreach::BelowMinimum];
        yield 'zero' => [0, QuantityBreach::BelowMinimum];
        yield 'the minimum' => [6, null];
        yield 'between two steps' => [7, QuantityBreach::OffIncrement];
        yield 'one past a step' => [12, QuantityBreach::OffIncrement];
        yield 'a step' => [16, null];
        yield 'the maximum' => [26, null];
        yield 'over the maximum' => [27, QuantityBreach::AboveMaximum];
        yield 'a step over the maximum' => [36, QuantityBreach::AboveMaximum];
    }

    #[DataProvider('quantities')]
    public function testItSaysHowAQuantityBreaksIt(int $quantity, ?QuantityBreach $breach): void
    {
        self::assertSame($breach, QuantityRule::of('DICE', null, 6, 26, 5)->breachOf($quantity));
    }

    public function testWithoutAMaximumAnyStepIsAllowed(): void
    {
        $rule = QuantityRule::of('DICE', null, 1, null, 1);

        self::assertNull($rule->breachOf(1));
        self::assertNull($rule->breachOf(1_000_000_000));
        self::assertSame(QuantityBreach::BelowMinimum, $rule->breachOf(0));
    }

    public function testItSuggestsTheClosestAllowedQuantity(): void
    {
        $rule = QuantityRule::of('DICE', null, 6, 26, 5);

        self::assertSame([6, 6, 6, 11, 11, 11, 26, null], array_map($rule->nextAllowed(...), [0, 5, 6, 7, 10, 11, 22, 27]));
        self::assertSame(1_000_000, QuantityRule::of('DICE')->nextAllowed(1_000_000));
    }

    public function testItsTermsChange(): void
    {
        $rule = QuantityRule::of('DICE', 'wholesale');
        $rule->change(12, 120, 12);

        self::assertSame([12, 120, 12], [$rule->minimum(), $rule->maximum(), $rule->increment()]);

        $rule->change(1, null, 1);
        self::assertSame([1, null, 1], [$rule->minimum(), $rule->maximum(), $rule->increment()]);
    }

    /**
     * @return iterable<string, array{string, ?string, int, ?int, int, string}>
     */
    public static function refused(): iterable
    {
        $terms = 'The quantities of %s start at 1 to 1000000 units, in steps of 1 to 1000000.';

        yield 'an empty SKU' => ['', null, 1, null, 1, '"" is not a SKU: 1 to 64 visible ASCII characters.'];
        yield 'a SKU too long' => [str_repeat('s', 65), null, 1, null, 1, \sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', str_repeat('s', 65))];
        yield 'a SKU with a space' => ['D 20', null, 1, null, 1, '"D 20" is not a SKU: 1 to 64 visible ASCII characters.'];
        yield 'a SKU ending in a line break' => ["DICE\n", null, 1, null, 1, "\"DICE\n\" is not a SKU: 1 to 64 visible ASCII characters."];
        yield 'an empty group' => ['DICE', '', 1, null, 1, '"" is not a customer group: 1 to 64 visible ASCII characters.'];
        yield 'a group too long' => ['DICE', str_repeat('g', 65), 1, null, 1, \sprintf('"%s" is not a customer group: 1 to 64 visible ASCII characters.', str_repeat('g', 65))];
        yield 'a group ending in a line break' => ['DICE', "retail\n", 1, null, 1, "\"retail\n\" is not a customer group: 1 to 64 visible ASCII characters."];
        yield 'a minimum of zero' => ['DICE', null, 0, null, 1, \sprintf($terms, 'DICE')];
        yield 'a minimum too high' => ['DICE', null, 1_000_001, null, 1, \sprintf($terms, 'DICE')];
        yield 'an increment of zero' => ['DICE', null, 1, null, 0, \sprintf($terms, 'DICE')];
        yield 'an increment too high' => ['DICE', null, 1, null, 1_000_001, \sprintf($terms, 'DICE')];
        yield 'a maximum under the minimum' => ['DICE', null, 6, 5, 1, 'The most units of DICE a line holds is at least its minimum of 6, and at most 1000000000.'];
        yield 'a maximum too high' => ['DICE', null, 6, 1_000_000_001, 1, 'The most units of DICE a line holds is at least its minimum of 6, and at most 1000000000.'];
    }

    #[DataProvider('refused')]
    public function testItRefusesTermsItCannotKeep(string $sku, ?string $group, int $minimum, ?int $maximum, int $increment, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        QuantityRule::of($sku, $group, $minimum, $maximum, $increment);
    }

    public function testTheLimitsThemselvesAreAccepted(): void
    {
        $rule = QuantityRule::of(str_repeat('s', 64), str_repeat('g', 64), 1_000_000, 1_000_000_000, 1_000_000);

        self::assertSame([1_000_000, 1_000_000_000, 1_000_000], [$rule->minimum(), $rule->maximum(), $rule->increment()]);
        self::assertSame(6, QuantityRule::of('DICE', null, 6, 6)->maximum());
    }

    public function testAChangeIsCheckedToo(): void
    {
        $rule = QuantityRule::of('DICE');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The most units of DICE a line holds is at least its minimum of 6, and at most 1000000000.');

        $rule->change(6, 5, 1);
    }
}
