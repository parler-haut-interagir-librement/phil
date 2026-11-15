<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\ExpirySweep;
use Phil\CommerceBundle\Domain\ValueObject\Identifier;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\WishListItem;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(OrderChangeLine::class)]
#[CoversClass(WishListItem::class)]
#[CoversClass(ExpirySweep::class)]
final class SmallValuesTest extends TestCase
{
    public function testAStableIdentifierIsKept(): void
    {
        self::assertSame('customer-42', Identifier::check('customer-42', 'customer'));
        self::assertSame(str_repeat('x', 255), Identifier::check(str_repeat('x', 255), 'customer'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'an email address' => ['bob@example.com'];
        yield 'a space' => ['bob smith'];
        yield 'too long' => [str_repeat('x', 256)];
        yield 'accented' => ['josé'];
    }

    #[DataProvider('refusedIdentifiers')]
    public function testAnIdentifierIsNeverAnEmailAddress(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not the stable identifier of a customer: 1 to 255 visible ASCII characters without "@", never an email address.', $value));

        Identifier::check($value, 'customer');
    }

    public function testAChangeLineSetsZeroTo9999Units(): void
    {
        $dice = SellableReference::of('product', 'dice');

        self::assertSame(0, OrderChangeLine::of($dice, 0)->quantity);
        self::assertSame(9_999, OrderChangeLine::of($dice, 9_999)->quantity);
        self::assertSame($dice, OrderChangeLine::of($dice, 3)->reference);

        foreach ([-1, 10_000] as $quantity) {
            try {
                OrderChangeLine::of($dice, $quantity);
                self::fail('Refused: '.$quantity);
            } catch (InvalidValue $refused) {
                self::assertSame('A change sets product:dice to 0 to 9999 units.', $refused->getMessage());
            }
        }
    }

    public function testAWishCountsWhatIsLeft(): void
    {
        $dice = SellableReference::of('product', 'dice');
        $item = WishListItem::of($dice, 3, 1);

        self::assertSame(3, $item->wanted);
        self::assertSame(1, $item->bought);
        self::assertSame(2, $item->remaining());
        self::assertSame(0, WishListItem::of($dice, 9_999, 9_999)->remaining());
        self::assertSame(0, WishListItem::of($dice, 1)->bought);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function refusedWishes(): iterable
    {
        yield 'none wanted' => [0, 0];
        yield 'too many wanted' => [10_000, 0];
        yield 'negative bought' => [2, -1];
        yield 'more bought than wanted' => [2, 3];
    }

    #[DataProvider('refusedWishes')]
    public function testAWishIsBounded(int $wanted, int $bought): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A wish for product:dice is of 1 to 9999 units, of which 0 to all are bought.');

        WishListItem::of(SellableReference::of('product', 'dice'), $wanted, $bought);
    }

    public function testASweepTellsWhatItDid(): void
    {
        $sweep = new ExpirySweep(['A'], ['B', 'C']);

        self::assertSame(['A'], $sweep->expired);
        self::assertSame(['B', 'C'], $sweep->deleted);
    }
}
