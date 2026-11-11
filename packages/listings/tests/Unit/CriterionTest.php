<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use Phil\Listings\Criterion;
use Phil\Listings\CriterionType;
use Phil\Listings\Exception\InvalidListing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Criterion::class)]
final class CriterionTest extends TestCase
{
    public function testAChoiceKeepsEachValueOnceInOrder(): void
    {
        $choice = Criterion::choice('day', ['sat', 'sun', 'sat']);

        self::assertSame('day', $choice->name);
        self::assertSame(CriterionType::Choice, $choice->type);
        self::assertSame(['sat', 'sun'], $choice->choices);
        self::assertNull($choice->min);
        self::assertNull($choice->max);
        self::assertNull($choice->flag);
    }

    public function testARangeKeepsItsEnds(): void
    {
        $range = Criterion::range('players', 3, 5);

        self::assertSame(CriterionType::Range, $range->type);
        self::assertSame(3, $range->min);
        self::assertSame(5, $range->max);
        self::assertSame([], $range->choices);
        self::assertNull($range->flag);
        self::assertSame(7, Criterion::range('players', 7, 7)->max);
    }

    public function testAFlagKeepsItsValue(): void
    {
        self::assertTrue(Criterion::flag('remote', true)->flag);
        self::assertFalse(Criterion::flag('remote', false)->flag);
        self::assertSame(CriterionType::Flag, Criterion::flag('remote', true)->type);
        self::assertNull(Criterion::flag('remote', true)->min);
        self::assertNull(Criterion::flag('remote', true)->max);
        self::assertSame([], Criterion::flag('remote', true)->choices);
    }

    public function testChoicesMeetWhenTheyShareAValue(): void
    {
        $listing = Criterion::choice('day', ['sat', 'sun']);

        self::assertTrue($listing->meets(Criterion::choice('day', ['sun', 'mon'])));
        self::assertFalse($listing->meets(Criterion::choice('day', ['mon'])));
        self::assertFalse($listing->meets(Criterion::choice('night', ['sat'])));
    }

    #[TestWith([3, 5, true])]
    #[TestWith([1, 3, true])]
    #[TestWith([5, 9, true])]
    #[TestWith([1, 2, false])]
    #[TestWith([6, 9, false])]
    #[TestWith([4, 4, true])]
    public function testRangesMeetWhenTheyOverlap(int $min, int $max, bool $meets): void
    {
        self::assertSame($meets, Criterion::range('players', 3, 5)->meets(Criterion::range('players', $min, $max)));
    }

    public function testFlagsMeetWhenEqual(): void
    {
        self::assertTrue(Criterion::flag('remote', true)->meets(Criterion::flag('remote', true)));
        self::assertTrue(Criterion::flag('remote', false)->meets(Criterion::flag('remote', false)));
        self::assertFalse(Criterion::flag('remote', true)->meets(Criterion::flag('remote', false)));
    }

    public function testCriteriaOfAnotherTypeNeverMeet(): void
    {
        self::assertFalse(Criterion::flag('x', false)->meets(Criterion::range('x', 0, 0)));
        self::assertFalse(Criterion::range('x', 0, 0)->meets(Criterion::flag('x', false)));
    }

    public function testAnInvertedRangeIsRefused(): void
    {
        $this->expectExceptionObject(InvalidListing::because('A range runs from its minimum to its maximum; 6 is above 5.'));

        Criterion::range('players', 6, 5);
    }

    #[TestWith([''])]
    #[TestWith(['Day'])]
    #[TestWith(['day.'])]
    #[TestWith(["day\n"])]
    #[TestWith(['nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn'])]
    public function testAnInvalidNameIsRefused(string $name): void
    {
        $this->expectExceptionObject(InvalidListing::because('A criterion name is 1 to 64 lowercase ASCII segments separated by ".", "_" or "-".'));

        Criterion::flag($name, true);
    }

    public function testANameOfSixtyFourBytesIsKept(): void
    {
        self::assertSame(str_repeat('n', 64), Criterion::range(str_repeat('n', 64), 0, 1)->name);
        self::assertSame(str_repeat('n', 64), Criterion::choice(str_repeat('n', 64), ['a'])->name);
    }

    #[TestWith([''])]
    #[TestWith(['-a'])]
    #[TestWith(["a\n"])]
    #[TestWith(['a b'])]
    #[TestWith(['ccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'])]
    public function testAnInvalidChoiceIsRefused(string $choice): void
    {
        $this->expectExceptionObject(InvalidListing::because('A choice is 1 to 64 ASCII letters, digits, ".", "_", ":" or "-".'));

        Criterion::choice('day', ['sat', $choice]);
    }

    public function testAChoiceHasOneToThirtyTwoValues(): void
    {
        self::assertSame(str_repeat('c', 64), Criterion::choice('day', [str_repeat('c', 64)])->choices[0]);
        self::assertCount(32, Criterion::choice('n', array_map(strval(...), range(1, 32)))->choices);

        try {
            Criterion::choice('day', []);
            self::fail('An empty choice was kept.');
        } catch (InvalidListing $invalidListing) {
            self::assertSame('A choice criterion has 1 to 32 distinct values.', $invalidListing->getMessage());
        }

        $this->expectException(InvalidListing::class);

        Criterion::choice('n', array_map(strval(...), range(1, 33)));
    }
}
