<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Collection;

use ArrayIterator;
use InvalidArgumentException;
use LogicException;
use Phil\Enums\Collection\EnumSet;
use Phil\Enums\Tests\Fixture\HighFlag;
use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\Permission;
use Phil\Enums\Tests\Fixture\Planet;
use Phil\Enums\Tests\Fixture\PureEnum;
use Phil\Enums\Tests\Fixture\StringBackedEnum;
use Phil\Enums\Tests\Fixture\ZeroFlag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnitEnum;

/**
 * @internal
 */
#[CoversClass(EnumSet::class)]
final class EnumSetTest extends TestCase
{
    #[Test]
    public function itHoldsEachCaseOnceInDeclarationOrder(): void
    {
        $set = EnumSet::of(Planet::class, Planet::Pluto, Planet::Mars, Planet::Pluto);

        self::assertSame([Planet::Mars, Planet::Pluto], iterator_to_array($set));
        self::assertSame([Planet::Mars, Planet::Pluto], $set->cases()->all());
        self::assertSame(['Mars', 'Pluto'], $set->names());
        self::assertSame(['c-mars', 'e-pluto'], $set->values());
        self::assertCount(2, $set);
        self::assertSame(Planet::class, $set->enum());
        self::assertTrue($set->contains(Planet::Mars));
        self::assertFalse($set->contains(Planet::Earth));
        self::assertFalse($set->isEmpty());
    }

    #[Test]
    public function fromValuesReadsTheValuesOrTheNamesOfAPureEnum(): void
    {
        self::assertSame([Permission::Read, Permission::Delete], iterator_to_array(EnumSet::fromValues(Permission::class, ['8', 1, 1])));
        self::assertSame([PureEnum::TWO], iterator_to_array(EnumSet::fromValues(PureEnum::class, new ArrayIterator(['TWO']))));
        self::assertTrue(EnumSet::fromValues(Planet::class, [])->isEmpty());
    }

    #[Test]
    public function fromValuesRefusesAValueNoCaseHas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('No case of enum '.Permission::class.' has the value 3, 4.');

        EnumSet::fromValues(Permission::class, [1, 3, 4, 3]);
    }

    #[Test]
    public function noneAndAllKnowTheirEnum(): void
    {
        self::assertTrue(EnumSet::none(Planet::class)->isEmpty());
        self::assertSame(Planet::class, EnumSet::none(Planet::class)->enum());
        self::assertSame(Planet::cases(), iterator_to_array(EnumSet::all(Planet::class)));
    }

    #[Test]
    public function itRefusesACaseOfAnotherEnum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(PureEnum::class.'::ONE is not a case of enum '.Planet::class.'.');

        EnumSet::of(Planet::class, Planet::Mars, PureEnum::ONE);
    }

    #[Test]
    public function setOperationsKeepDeclarationOrderAndLeaveTheSetsAlone(): void
    {
        $inner = EnumSet::of(Planet::class, Planet::Venus, Planet::Earth);
        $other = EnumSet::of(Planet::class, Planet::Earth, Planet::Pluto);

        self::assertSame([Planet::Earth, Planet::Venus, Planet::Pluto], iterator_to_array($inner->union($other)));
        self::assertSame([Planet::Earth], iterator_to_array($inner->intersect($other)));
        self::assertSame([Planet::Venus], iterator_to_array($inner->diff($other)));
        self::assertSame([Planet::Mars, Planet::Mercury, Planet::Pluto], iterator_to_array($inner->complement()));
        self::assertSame([Planet::Mars, Planet::Earth, Planet::Venus], iterator_to_array($inner->with(Planet::Mars)));
        self::assertSame([Planet::Earth], iterator_to_array($inner->without(Planet::Venus, Planet::Pluto)));
        self::assertSame([Planet::Earth, Planet::Venus], iterator_to_array($inner));
    }

    #[Test]
    public function equalityAndInclusion(): void
    {
        $inner = EnumSet::of(Planet::class, Planet::Venus, Planet::Earth);

        self::assertTrue($inner->equals(EnumSet::of(Planet::class, Planet::Earth, Planet::Venus)));
        self::assertFalse($inner->equals(EnumSet::of(Planet::class, Planet::Earth)));
        self::assertFalse(EnumSet::none(Planet::class)->equals(EnumSet::none(PureEnum::class)));
        self::assertTrue(EnumSet::of(Planet::class, Planet::Earth)->isSubsetOf($inner));
        self::assertTrue($inner->isSubsetOf($inner));
        self::assertFalse($inner->isSubsetOf(EnumSet::of(Planet::class, Planet::Earth)));
        self::assertTrue($inner->isSupersetOf(EnumSet::of(Planet::class, Planet::Earth)));
        self::assertFalse(EnumSet::of(Planet::class, Planet::Earth)->isSupersetOf($inner));
    }

    #[Test]
    public function unionRefusesASetOfAnotherEnum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A set of enum '.Planet::class.' cannot be combined with a set of enum '.PureEnum::class.'.');

        EnumSet::none(Planet::class)->union(EnumSet::none(PureEnum::class));
    }

    #[Test]
    public function intersectRefusesASetOfAnotherEnum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A set of enum '.Planet::class.' cannot be combined with a set of enum '.PureEnum::class.'.');

        EnumSet::none(Planet::class)->intersect(EnumSet::none(PureEnum::class));
    }

    #[Test]
    public function diffRefusesASetOfAnotherEnum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A set of enum '.Planet::class.' cannot be combined with a set of enum '.PureEnum::class.'.');

        EnumSet::none(Planet::class)->diff(EnumSet::none(PureEnum::class));
    }

    #[Test]
    public function aSetOfFlagsGoesToAndComesBackFromABitmask(): void
    {
        $set = EnumSet::of(Permission::class, Permission::Read, Permission::Delete);

        self::assertSame(9, $set->toBitmask());
        self::assertSame(0, EnumSet::none(Permission::class)->toBitmask());
        self::assertSame(11, EnumSet::all(Permission::class)->toBitmask());
        self::assertTrue($set->equals(EnumSet::fromBitmask(Permission::class, 9)));
        self::assertTrue(EnumSet::fromBitmask(Permission::class, 0)->isEmpty());
        self::assertSame([Permission::Write], iterator_to_array(EnumSet::fromBitmask(Permission::class, 2)));
    }

    #[Test]
    public function aBitmaskWithABitNoCaseStandsForIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The bitmask 13 has bits no case of enum '.Permission::class.' stands for: 4.');

        EnumSet::fromBitmask(Permission::class, 13);
    }

    #[Test]
    public function aBitBelowTheFlagsIsRefused(): void
    {
        self::assertSame(6, EnumSet::all(HighFlag::class)->toBitmask());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('stands for: 1.');

        EnumSet::fromBitmask(HighFlag::class, 1);
    }

    #[Test]
    public function aNegativeBitmaskIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EnumSet::fromBitmask(Permission::class, -1);
    }

    /**
     * @return iterable<string, array{class-string<UnitEnum>, string}>
     */
    public static function notFlags(): iterable
    {
        yield 'pure' => [PureEnum::class, PureEnum::class.'::ONE'];
        yield 'backed by strings' => [StringBackedEnum::class, StringBackedEnum::class.'::DRAFT'];
        yield 'not a power of two' => [IntBackedEnum::class, IntBackedEnum::class.'::THREE'];
        yield 'zero' => [ZeroFlag::class, ZeroFlag::class.'::None'];
    }

    /**
     * @param class-string<UnitEnum> $enum
     */
    #[Test]
    #[DataProvider('notFlags')]
    public function anEnumOfNoBitFlagsHasNoBitmask(string $enum, string $case): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains($case.' is not a bit flag: a bitmask needs an enum backed by integers that are powers of two.');

        EnumSet::none($enum)->toBitmask();
    }

    /**
     * @param class-string<UnitEnum> $enum
     */
    #[Test]
    #[DataProvider('notFlags')]
    public function anEnumOfNoBitFlagsReadsNoBitmask(string $enum, string $case): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains($case.' is not a bit flag');

        EnumSet::fromBitmask($enum, 0);
    }
}
