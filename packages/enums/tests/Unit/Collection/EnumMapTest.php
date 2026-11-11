<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Collection;

use InvalidArgumentException;
use Phil\Enums\Collection\EnumMap;
use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\Planet;
use Phil\Enums\Tests\Fixture\PureEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnhandledMatchError;

/**
 * @internal
 */
#[CoversClass(EnumMap::class)]
final class EnumMapTest extends TestCase
{
    #[Test]
    public function itHoldsAValueForEveryCaseAndIteratesWithTheCasesAsKeys(): void
    {
        $map = EnumMap::total(PureEnum::class, static fn (PureEnum $case): int => match ($case) {
            PureEnum::ONE => 1,
            PureEnum::TWO => 2,
            PureEnum::THREE => 3,
        });

        self::assertSame(2, $map->get(PureEnum::TWO));
        self::assertSame([1, 2, 3], $map->values());
        self::assertSame(PureEnum::cases(), $map->keys()->all());
        self::assertSame(['ONE' => 1, 'TWO' => 2, 'THREE' => 3], $map->toArray());
        self::assertSame(PureEnum::class, $map->enum());
        self::assertCount(3, $map);

        $pairs = [];

        foreach ($map as $case => $value) {
            $pairs[] = [$case, $value];
        }

        self::assertSame([[PureEnum::ONE, 1], [PureEnum::TWO, 2], [PureEnum::THREE, 3]], $pairs);
    }

    #[Test]
    public function aForgottenCaseFailsWhereTheMapIsMade(): void
    {
        $this->expectException(UnhandledMatchError::class);

        EnumMap::total(PureEnum::class, static fn (PureEnum $case): int => match ($case) { // @phpstan-ignore match.unhandled
            PureEnum::ONE => 1,
            PureEnum::TWO => 2,
        });
    }

    #[Test]
    public function itIsReadFromAnArrayKeyedByValuesAndWrittenBackSo(): void
    {
        $map = EnumMap::fromArray(IntBackedEnum::class, [3 => 'c', 1 => 'a', 2 => 'b']);

        self::assertSame(['a', 'b', 'c'], $map->values());
        self::assertSame([1 => 'a', 2 => 'b', 3 => 'c'], $map->toArray());
        self::assertSame('c', $map->get(IntBackedEnum::THREE));
    }

    #[Test]
    public function anArrayMissingACaseOrWithAnUnknownKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('An enum map of '.Planet::class.' needs a value for every case and nothing else; missing: [b-earth, e-pluto], unknown: [x, y].');

        EnumMap::fromArray(Planet::class, ['c-mars' => 1, 'a-mercury' => 3, 'x' => 0, 'd-venus' => 4, 'y' => 0]);
    }

    #[Test]
    public function anArrayMissingOnlyACaseIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('missing: [THREE], unknown: [].');

        EnumMap::fromArray(PureEnum::class, ['ONE' => 1, 'TWO' => 2]);
    }

    #[Test]
    public function anArrayWithOnlyAnUnknownKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('missing: [], unknown: [FOUR].');

        EnumMap::fromArray(PureEnum::class, ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4]);
    }

    #[Test]
    public function aPartialArrayTakesTheDefaultForTheCasesItLeavesOut(): void
    {
        self::assertSame([1 => 'a', 2 => '?', 3 => 'c'], EnumMap::fromPartial(IntBackedEnum::class, [3 => 'c', 1 => 'a'], '?')->toArray());
        self::assertSame([null, null, null], EnumMap::fromPartial(PureEnum::class, [], null)->values());
    }

    #[Test]
    public function aPartialArrayWithAnUnknownKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('missing: [], unknown: [FOUR].');

        EnumMap::fromPartial(PureEnum::class, ['FOUR' => 4], 0);
    }

    #[Test]
    public function withAndMapReturnNewMaps(): void
    {
        $map = EnumMap::fromArray(PureEnum::class, ['ONE' => 1, 'TWO' => 2, 'THREE' => 3]);
        $changed = $map->with(PureEnum::TWO, 20);

        self::assertSame([1, 20, 3], $changed->values());
        self::assertSame(['ONE' => 1, 'TWO' => 20, 'THREE' => 3], $changed->toArray());
        self::assertSame([1, 2, 3], $map->values());
        self::assertSame(['ONE:1', 'TWO:2', 'THREE:3'], $map->map(static fn (int $value, PureEnum $case): string => $case->name.':'.$value)->values());
    }

    #[Test]
    public function getRefusesACaseOfAnotherEnum(): void
    {
        $map = EnumMap::total(PureEnum::class, static fn (): int => 0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(Planet::class.'::Mars is not a case of enum '.PureEnum::class.'.');

        $map->get(Planet::Mars);
    }

    #[Test]
    public function withRefusesACaseOfAnotherEnum(): void
    {
        $map = EnumMap::total(PureEnum::class, static fn (): int => 0);

        $this->expectException(InvalidArgumentException::class);

        $map->with(Planet::Mars, 1);
    }
}
