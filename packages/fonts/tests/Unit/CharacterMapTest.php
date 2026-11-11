<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\Reader\Binary;
use Phil\Fonts\Reader\CharacterMap;
use Phil\Fonts\Tests\Unit\Fixture\FontBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CharacterMap::class)]
final class CharacterMapTest extends TestCase
{
    /**
     * @param list<array{int, int, string}> $subtables
     *
     * @return list<array{int, int}>
     */
    private function ranges(array $subtables): array
    {
        return CharacterMap::ranges(new Binary(FontBuilder::cmap($subtables)));
    }

    public function testReadsTheSegmentsOfFormat4ButTheLastOne(): void
    {
        self::assertSame([[0x20, 0x7E], [0xA0, 0xFF], [0x2013, 0x2014]], $this->ranges([[3, 1, FontBuilder::format4([[0x20, 0x7E], [0xA0, 0xFF], [0x2013, 0x2014]])]]));
    }

    public function testPrefersFormat12WhereverItIs(): void
    {
        self::assertSame([[0x41, 0x5A], [0x1F600, 0x1F64F]], $this->ranges([
            [0, 3, FontBuilder::format4([[0x20, 0x7E]])],
            [3, 10, FontBuilder::format12([[0x41, 0x5A], [0x1F600, 0x1F64F]])],
        ]));
        self::assertSame([[0x30, 0x39]], $this->ranges([[0, 4, FontBuilder::format12([[0x30, 0x39]])], [3, 1, FontBuilder::format4([[0x20, 0x7E]])]]));
    }

    public function testTakesTheFirstFormat4(): void
    {
        self::assertSame([[0x30, 0x39]], $this->ranges([[0, 3, FontBuilder::format4([[0x30, 0x39]])], [3, 1, FontBuilder::format4([[0x20, 0x7E]])]]));
    }

    public function testReadsSymbolAndUnicodePlatformsOnly(): void
    {
        self::assertSame([[0xF020, 0xF0FF]], $this->ranges([[1, 0, FontBuilder::format12([[0x20, 0x7E]])], [3, 0, FontBuilder::format4([[0xF020, 0xF0FF]])]]));
        self::assertSame([], $this->ranges([[1, 0, FontBuilder::format4([[0x20, 0x7E]])], [3, 2, FontBuilder::format4([[0x20, 0x7E]])], [3, 10, FontBuilder::format12([])]]));
        self::assertSame([], $this->ranges([[3, 1, FontBuilder::u16(6).str_repeat("\0", 8)]]));
        self::assertSame([], $this->ranges([]));
    }

    public function testReadsNoRunBeforeTheFirstSegmentOrGroup(): void
    {
        self::assertSame([[1, 5]], self::ranges([[3, 1, FontBuilder::format4([[1, 5]])]]));
        self::assertSame([[0x30, 0x39]], self::ranges([[3, 10, FontBuilder::format12([[0x30, 0x39]], language: 0xFFFFFFFF)]]));
    }

    public function testSortsAndMergesTouchingRuns(): void
    {
        self::assertSame([[1, 20]], self::ranges([[3, 10, FontBuilder::format12([[5, 10], [1, 20]])]]));
        self::assertSame([[1, 20]], self::ranges([[3, 10, FontBuilder::format12([[1, 20], [5, 10]])]]));
        self::assertSame([[1, 5], [7, 9]], self::ranges([[3, 10, FontBuilder::format12([[7, 9], [1, 5]])]]));
        self::assertSame(
            [[0x10, 0x30], [0x40, 0x50], [0x60, 0x60]],
            $this->ranges([[3, 10, FontBuilder::format12([[0x40, 0x50], [0x20, 0x30], [0x10, 0x1F], [0x22, 0x25], [0x60, 0x60], [0x70, 0x6F]])]]),
        );
        self::assertSame([[0x10, 0x30]], $this->ranges([[3, 10, FontBuilder::format12([[0x10, 0x30], [0x31, 0x20]])]]));
    }
}
