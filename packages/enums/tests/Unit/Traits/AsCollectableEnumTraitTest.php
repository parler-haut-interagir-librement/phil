<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Sort\SortDirection;
use Phil\Enums\Tests\Fixture\Attribute\Mass;
use Phil\Enums\Tests\Fixture\Planet;
use Phil\Enums\Traits\AsCollectableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The operations on the enum itself, each over every case in declaration order.
 *
 * @internal
 */
#[CoversTrait(AsCollectableEnumTrait::class)]
final class AsCollectableEnumTraitTest extends TestCase
{
    #[Test]
    public function theEnumCountsAndCollectsItsCases(): void
    {
        self::assertSame(5, Planet::count());
        self::assertSame(Planet::cases(), Planet::collect()->all());
    }

    #[Test]
    public function theEnumTakesItsFirstAndLastCases(): void
    {
        self::assertSame(Planet::Mars, Planet::first());
        self::assertSame(Planet::Pluto, Planet::last());
        self::assertSame(Planet::Earth, Planet::first(static fn (Planet $planet): bool => $planet->mass() > 40));
        self::assertSame(Planet::Venus, Planet::last(static fn (Planet $planet): bool => $planet->mass() > 40));
    }

    #[Test]
    public function theEnumFiltersAndSortsItsCases(): void
    {
        self::assertSame([Planet::Mercury], Planet::filter(static fn (Planet $planet): bool => 3 === $planet->mass())->all());
        self::assertSame([Planet::Venus], Planet::whereMeta(Mass::class, 49)->all());
        self::assertSame([Planet::Pluto], Planet::inGroup('dwarf')->all());
        self::assertSame(Planet::Earth, Planet::sortBy(static fn (Planet $planet): ?int => $planet->mass(), true)->first());
        self::assertSame(Planet::Pluto, Planet::sortBy(static fn (Planet $planet): ?int => $planet->mass())->first());
    }

    /**
     * The enum's `sortBy()` takes the direction as `CaseCollection::sortBy()` does.
     */
    #[Test]
    public function theEnumSortsInADirectionGivenAsSortDirection(): void
    {
        self::assertSame(Planet::Earth, Planet::sortBy(static fn (Planet $planet): ?int => $planet->mass(), SortDirection::Descending)->first());
        self::assertSame(Planet::Pluto, Planet::sortBy(static fn (Planet $planet): ?int => $planet->mass(), SortDirection::Ascending)->first());
    }
}
