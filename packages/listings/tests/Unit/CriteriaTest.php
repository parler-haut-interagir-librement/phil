<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use Phil\Listings\Criteria;
use Phil\Listings\Criterion;
use Phil\Listings\Exception\InvalidListing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Criteria::class)]
final class CriteriaTest extends TestCase
{
    public function testCriteriaAreKeptByName(): void
    {
        $day = Criterion::choice('day', ['sat']);
        $players = Criterion::range('players', 3, 5);
        $criteria = Criteria::of([$day, $players]);

        self::assertSame([$day, $players], $criteria->all());
        self::assertSame($players, $criteria->get('players'));
        self::assertNull($criteria->get('remote'));
        self::assertSame([], Criteria::of()->all());
    }

    public function testEveryWantedCriterionMustBeMet(): void
    {
        $listing = Criteria::of([Criterion::choice('day', ['sat', 'sun']), Criterion::range('players', 3, 5), Criterion::flag('beginners', true)]);

        self::assertTrue($listing->meets(Criteria::of()));
        self::assertTrue($listing->meets(Criteria::of([Criterion::choice('day', ['sun']), Criterion::range('players', 4, 8)])));
        self::assertFalse($listing->meets(Criteria::of([Criterion::choice('day', ['sun']), Criterion::range('players', 6, 8)])));
        self::assertFalse($listing->meets(Criteria::of([Criterion::flag('remote', true)])));
        self::assertFalse(Criteria::of()->meets(Criteria::of([Criterion::flag('remote', true)])));
    }

    public function testANameIsGivenOnce(): void
    {
        $this->expectExceptionObject(InvalidListing::because('The criterion "day" is given twice.'));

        Criteria::of([Criterion::choice('day', ['sat']), Criterion::flag('day', true)]);
    }

    public function testThereAreAtMostThirtyTwoCriteria(): void
    {
        $flags = array_map(static fn (int $n): Criterion => Criterion::flag('f'.$n, true), range(1, 33));

        self::assertCount(32, Criteria::of(\array_slice($flags, 0, 32))->all());

        $this->expectExceptionObject(InvalidListing::because('A listing has at most 32 criteria.'));

        Criteria::of($flags);
    }
}
