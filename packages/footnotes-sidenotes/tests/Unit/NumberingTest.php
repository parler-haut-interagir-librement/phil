<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Generator;
use Phil\FootnotesSidenotes\Exception\InvalidOptions;
use Phil\FootnotesSidenotes\Numbering;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Numbering::class)]
#[CoversClass(InvalidOptions::class)]
final class NumberingTest extends TestCase
{
    /**
     * @return Generator<string, array{Numbering, int, string}>
     */
    public static function labels(): Generator
    {
        yield 'decimal' => [Numbering::decimal(), 12, '12'];
        yield 'lower roman' => [Numbering::lowerRoman(), 14, 'xiv'];
        yield 'upper roman' => [Numbering::upperRoman(), 1994, 'MCMXCIV'];
        yield 'the last roman' => [Numbering::upperRoman(), 3999, 'MMMCMXCIX'];
        yield 'past the last roman' => [Numbering::upperRoman(), 4000, '4000'];
        yield 'the first symbol' => [Numbering::symbols(), 1, '*'];
        yield 'the last symbol of a round' => [Numbering::symbols(), 6, '¶'];
        yield 'doubled' => [Numbering::symbols(), 7, '**'];
        yield 'tripled' => [Numbering::symbols(), 18, '¶¶¶'];
        yield 'past three rounds' => [Numbering::symbols(), 19, '19'];
        yield 'own symbols' => [Numbering::symbols(['a', 'b']), 4, 'bb'];
    }

    #[DataProvider('labels')]
    public function testItWritesTheNumber(Numbering $numbering, int $number, string $label): void
    {
        self::assertSame($label, $numbering->format($number));
    }

    /**
     * @return Generator<string, array{list<string>, string}>
     */
    public static function refusedSymbols(): Generator
    {
        yield 'none' => [[], 'at least one symbol'];
        yield 'empty' => [['*', ''], '1 to 8 characters'];
        yield 'too long' => [['123456789'], '1 to 8 characters'];
        yield 'twice' => [['*', '*'], 'all be different'];
    }

    /**
     * @param list<string> $symbols
     */
    #[DataProvider('refusedSymbols')]
    public function testItRefusesSymbols(array $symbols, string $message): void
    {
        $this->expectException(InvalidOptions::class);
        $this->expectExceptionMessageIsOrContains($message);

        Numbering::symbols($symbols);
    }

    public function testASymbolMayFillEightCharactersCountedAsCharacters(): void
    {
        self::assertSame('††††††††', Numbering::symbols(['††††††††'])->format(1));
    }
}
