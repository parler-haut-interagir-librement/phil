<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\WishList;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\WishListItem;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WishList::class)]
final class WishListTest extends TestCase
{
    public function testAListIsCreatedEmptyAndUnshared(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $list = WishList::create('member-1', 'Birthday', $at);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $list->id);
        self::assertSame('member-1', $list->owner);
        self::assertSame('Birthday', $list->name());
        self::assertSame($at, $list->createdAt);
        self::assertSame([], $list->items());
        self::assertFalse($list->isShared());
        self::assertSame(1, $list->version());

        $list->rename('Holidays');
        self::assertSame('Holidays', $list->name());
    }

    public function testWishesAddUpAndPurchasesCountWhatIsLeft(): void
    {
        $list = WishList::create('member-1', 'Birthday', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');
        $mat = SellableReference::of('product', 'mat');

        $list->wish($dice, 2);
        $list->wish($mat, 1);
        $list->wish($dice, 1);

        self::assertEquals([WishListItem::of($dice, 3), WishListItem::of($mat, 1)], $list->items());
        self::assertSame(2, $list->recordPurchase($dice, 2));
        self::assertSame(1, $list->recordPurchase($dice, 5));
        self::assertSame(0, $list->recordPurchase($dice, 1));
        self::assertSame(0, $list->recordPurchase($mat, 0));
        self::assertSame(0, $list->recordPurchase(SellableReference::of('product', 'pen'), 1));
        self::assertEquals(WishListItem::of($dice, 3, 3), $list->item($dice));
        self::assertEquals(WishListItem::of($mat, 1, 0), $list->item($mat));

        $list->forget($mat);
        self::assertNull($list->item($mat));
        self::assertCount(1, $list->items());
    }

    public function testAThingIsWishedForByOneUnitOrMore(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A thing is wished for by one unit or more.');

        WishList::create('member-1', 'Birthday', new DateTimeImmutable())->wish(SellableReference::of('product', 'dice'), 0);
    }

    public function testAListHoldsAtMost500Things(): void
    {
        $list = WishList::create('member-1', 'Birthday', new DateTimeImmutable());

        for ($i = 0; $i < WishList::MAX_ITEMS; ++$i) {
            $list->wish(SellableReference::of('product', 'p'.$i), 1);
        }

        // More of a thing already there is still accepted.
        $list->wish(SellableReference::of('product', 'p0'), 1);
        self::assertSame(2, $list->item(SellableReference::of('product', 'p0'))?->wanted);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A wish list holds at most 500 things.');

        $list->wish(SellableReference::of('product', 'one-more'), 1);
    }

    public function testAListIsSharedUnderAHashAndStopsBeingShared(): void
    {
        $list = WishList::create('member-1', 'Birthday', new DateTimeImmutable());
        $hash = hash('sha256', 'token');

        $list->shareUnder($hash);
        self::assertTrue($list->isShared());
        self::assertTrue($list->isSharedUnder($hash));
        self::assertFalse($list->isSharedUnder(hash('sha256', 'other')));

        $list->stopSharing();
        self::assertFalse($list->isShared());
        self::assertFalse($list->isSharedUnder($hash));
    }

    public function testAListIsSharedUnderAHexadecimalHash(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A wish list is shared under the hexadecimal SHA-256 of its token.');

        WishList::create('member-1', 'Birthday', new DateTimeImmutable())->shareUnder('token');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedNames(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [' '];
        yield 'too long' => [str_repeat('n', 256)];
        yield 'two lines' => ["a\nb"];
    }

    #[DataProvider('refusedNames')]
    public function testAListHasAName(string $name): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A wish list has a name: 1 to 255 characters on one line.');

        WishList::create('member-1', $name, new DateTimeImmutable());
    }

    public function testTheOwnerIsAStableIdentifier(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not the stable identifier of a wish list owner');

        WishList::create('bob@example.com', 'Birthday', new DateTimeImmutable());
    }

    public function testAWishForMoreOfAThingKeepsWhatWasAlreadyBought(): void
    {
        $list = WishList::create('member-1', 'Birthday', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');

        $list->wish($dice, 3);
        $list->recordPurchase($dice, 2);
        $list->wish($dice, 1);

        self::assertEquals(WishListItem::of($dice, 4, 2), $list->item($dice));
    }

    public function testASingleUnitBoughtIsCounted(): void
    {
        $list = WishList::create('member-1', 'Birthday', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');
        $list->wish($dice, 2);

        self::assertSame(1, $list->recordPurchase($dice, 1));
        self::assertEquals(WishListItem::of($dice, 2, 1), $list->item($dice));
    }
}
