<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Collection;

use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Sort\SortDirection;
use Phil\Enums\Tests\Fixture\Attribute\Mass;
use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\Planet;
use Phil\Enums\Tests\Fixture\PureEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CaseCollection::class)]
final class CaseCollectionTest extends TestCase
{
    #[Test]
    public function itHoldsTheCasesAsAListInTheirOrder(): void
    {
        $collection = CaseCollection::of(['a' => Planet::Venus, 'b' => Planet::Mars]);

        self::assertSame([Planet::Venus, Planet::Mars], $collection->all());
        self::assertSame([Planet::Venus, Planet::Mars], iterator_to_array($collection));
        self::assertCount(2, $collection);
        self::assertFalse($collection->isEmpty());
        self::assertTrue(CaseCollection::of([])->isEmpty());
    }

    #[Test]
    public function firstAndLastTakeTheEndsOrTheFirstAndLastAccepted(): void
    {
        $planets = Planet::collect();
        $heavy = static fn (Planet $planet): bool => $planet->mass() > 40;

        self::assertSame(Planet::Mars, $planets->first());
        self::assertSame(Planet::Pluto, $planets->last());
        self::assertSame(Planet::Earth, $planets->first($heavy));
        self::assertSame(Planet::Venus, $planets->last($heavy));
        self::assertNull($planets->first(static fn (): bool => false));
        self::assertNull(CaseCollection::of([])->first());
        self::assertNull(CaseCollection::of([])->last());
    }

    #[Test]
    public function filterAndRejectKeepAListAndLeaveTheCollectionAlone(): void
    {
        $planets = Planet::collect();
        $heavy = static fn (Planet $planet): bool => $planet->mass() > 40;

        self::assertSame([Planet::Earth, Planet::Venus], $planets->filter($heavy)->all());
        self::assertSame([Planet::Mars, Planet::Mercury, Planet::Pluto], $planets->reject($heavy)->all());
        self::assertCount(5, $planets);
    }

    #[Test]
    public function onlyAndExceptKeepTheOrderOfTheCollection(): void
    {
        $planets = Planet::collect();

        self::assertSame([Planet::Mars, Planet::Venus], $planets->only(Planet::Venus, Planet::Mars)->all());
        self::assertSame([Planet::Earth, Planet::Mercury, Planet::Pluto], $planets->except(Planet::Venus, Planet::Mars)->all());
        self::assertTrue($planets->contains(Planet::Pluto));
        self::assertFalse($planets->except(Planet::Pluto)->contains(Planet::Pluto));
    }

    /**
     * A case without the attribute has the property's default value, `null` here.
     */
    #[Test]
    public function whereMetaComparesTheValueStrictly(): void
    {
        self::assertSame([Planet::Earth], Planet::collect()->whereMeta(Mass::class, 60)->all());
        self::assertSame([], Planet::collect()->whereMeta(Mass::class, '60')->all());
        self::assertSame([Planet::Pluto], Planet::collect()->whereMeta(Mass::class, null)->all());
    }

    #[Test]
    public function inGroupKeepsTheCasesInAnyOfTheGroups(): void
    {
        self::assertSame([Planet::Mars, Planet::Earth, Planet::Mercury, Planet::Venus], Planet::collect()->inGroup('inner')->all());
        self::assertSame([Planet::Earth, Planet::Pluto], Planet::collect()->inGroup('home', 'dwarf')->all());
        self::assertSame([], Planet::collect()->inGroup('outer')->all());
    }

    #[Test]
    public function sortByOrdersByTheKeyAscendingOrDescending(): void
    {
        $mass = static fn (Planet $planet): ?int => $planet->mass();

        self::assertSame([Planet::Pluto, Planet::Mercury, Planet::Mars, Planet::Venus, Planet::Earth], Planet::collect()->sortBy($mass)->all());
        self::assertSame([Planet::Earth, Planet::Venus, Planet::Mars, Planet::Mercury, Planet::Pluto], Planet::collect()->sortBy($mass, true)->all());
        self::assertSame([Planet::Earth, Planet::Venus, Planet::Mars, Planet::Mercury, Planet::Pluto], Planet::collect()->sortBy($mass, SortDirection::Descending)->all());
        self::assertSame([Planet::Pluto, Planet::Mercury, Planet::Mars, Planet::Venus, Planet::Earth], Planet::collect()->sortBy($mass, SortDirection::Ascending)->all());
    }

    #[Test]
    public function sortByIsStable(): void
    {
        $same = static fn (): int => 0;

        self::assertSame(Planet::cases(), Planet::collect()->sortBy($same)->all());
        self::assertSame(Planet::cases(), Planet::collect()->sortBy($same, true)->all());
    }

    #[Test]
    public function casesSortByNameOrByValue(): void
    {
        self::assertSame(['Earth', 'Mars', 'Mercury', 'Pluto', 'Venus'], Planet::collect()->sortByName()->names());
        self::assertSame(['Venus', 'Pluto', 'Mercury', 'Mars', 'Earth'], Planet::collect()->sortByName(true)->names());
        self::assertSame(['a-mercury', 'b-earth', 'c-mars', 'd-venus', 'e-pluto'], Planet::collect()->sortByValue()->values());
        self::assertSame(['e-pluto', 'd-venus', 'c-mars', 'b-earth', 'a-mercury'], Planet::collect()->sortByValue(true)->values());
        self::assertSame(['e-pluto', 'd-venus', 'c-mars', 'b-earth', 'a-mercury'], Planet::collect()->sortByValue(SortDirection::Descending)->values());
        self::assertSame(['Venus', 'Pluto', 'Mercury', 'Mars', 'Earth'], Planet::collect()->sortByName(SortDirection::Descending)->names());
        self::assertSame(['ONE', 'THREE', 'TWO'], CaseCollection::of(PureEnum::cases())->sortByValue()->values());
    }

    #[Test]
    public function reverseAndMap(): void
    {
        self::assertSame([3, 2, 1], CaseCollection::of(IntBackedEnum::cases())->reverse()->values());
        self::assertSame(['Mars!', 'Earth!'], Planet::collect()->only(Planet::Mars, Planet::Earth)->map(static fn (Planet $planet): string => $planet->name.'!'));
    }

    #[Test]
    public function namesValuesAndOptionsOfTheCasesKept(): void
    {
        $heavy = Planet::collect()->filter(static fn (Planet $planet): bool => $planet->mass() > 40);

        self::assertSame(['Earth', 'Venus'], $heavy->names());
        self::assertSame(['b-earth', 'd-venus'], $heavy->values());
        self::assertSame(['Earth' => 'b-earth', 'Venus' => 'd-venus'], $heavy->options());
        self::assertSame(['ONE', 'TWO'], CaseCollection::of([PureEnum::ONE, PureEnum::TWO])->options());
    }

    #[Test]
    public function aCollectionKnowsWhetherItsCasesAreBacked(): void
    {
        self::assertTrue(Planet::collect()->isBacked());
        self::assertFalse(CaseCollection::of(PureEnum::cases())->isBacked());
        self::assertFalse(CaseCollection::of([])->isBacked());
        self::assertTrue(CaseCollection::of([Planet::Mars])->isBacked());
    }
}
