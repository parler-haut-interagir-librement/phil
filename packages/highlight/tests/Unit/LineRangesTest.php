<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit;

use Phil\Highlight\Exception\HighlightException;
use Phil\Highlight\Exception\InvalidLineRanges;
use Phil\Highlight\LineRanges;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineRanges::class)]
#[CoversClass(InvalidLineRanges::class)]
final class LineRangesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{int, int}>, string}>
     */
    public static function texts(): iterable
    {
        yield 'one line' => ['3', [[3, 3]], '3'];
        yield 'a range' => ['3-5', [[3, 5]], '3-5'];
        yield 'lines and ranges' => ['1,3-5,9', [[1, 1], [3, 5], [9, 9]], '1,3-5,9'];
        yield 'spaces and tabs' => [" 2 ,\t7 - 9 ", [[2, 2], [7, 9]], '2,7-9'];
        yield 'sorted' => ['9,1', [[1, 1], [9, 9]], '1,9'];
        yield 'overlapping, merged' => ['1-4,3-6', [[1, 6]], '1-6'];
        yield 'touching, merged' => ['1-2,3', [[1, 3]], '1-3'];
        yield 'contained, merged' => ['1-9,3-4', [[1, 9]], '1-9'];
        yield 'a line inside a later range' => ['5,3-6', [[3, 6]], '3-6'];
        yield 'a range written first' => ['9-12,2-3', [[2, 3], [9, 12]], '2-3,9-12'];
        yield 'one line repeated' => ['4,4', [[4, 4]], '4'];
        yield 'apart by one line' => ['1,3', [[1, 1], [3, 3]], '1,3'];
        yield 'leading zeros' => ['007', [[7, 7]], '7'];
        yield 'the greatest line' => ['1000000', [[1_000_000, 1_000_000]], '1000000'];
        yield 'empty' => ['', [], ''];
        yield 'blank' => ['  ', [], ''];
    }

    /**
     * @param list<array{int, int}> $ranges
     */
    #[DataProvider('texts')]
    public function testItReadsLinesAndRanges(string $text, array $ranges, string $written): void
    {
        $parsed = LineRanges::parse($text);

        self::assertSame($ranges, $parsed->toArray());
        self::assertSame($written, $parsed->toString());
        self::assertSame($written, (string) $parsed);
        self::assertSame([] === $ranges, $parsed->isEmpty());
        self::assertEquals($parsed, LineRanges::parse($parsed->toString()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'a word' => ['a', '"a" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'line 0' => ['0', '"0" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'a range from 0' => ['0-3', '"0" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'negative' => ['-3', '"-3" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'an empty part' => ['1,,2', '"1,,2" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'a trailing comma' => ['1,', '"1," are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'an open range' => ['3-', '"3-" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'two dashes' => ['1-2-3', '"1-2-3" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'a newline' => ["1\n", "\"1\n\" are not line ranges: write lines and ranges from 1 separated by commas, such as \"1,3-5\"."];
        yield 'reversed' => ['5-3', 'The range 5-3 ends before it starts.'];
        yield 'too large' => ['1000001', 'A line number is 1000000 at most.'];
        yield 'too large at the end' => ['1-1000001', 'A line number is 1000000 at most.'];
        yield 'eight digits' => ['10000000', '"10000000" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'too many' => [implode(',', range(1, 257)), 'Line ranges hold 256 ranges at most.'];
        yield 'far too many' => [implode(',', range(1, 300)), 'Line ranges hold 256 ranges at most.'];
        yield 'too many, the last unread' => [str_repeat('1,', 256).'x', 'Line ranges hold 256 ranges at most.'];
        yield 'a long text, cut' => ['ab'.str_repeat('x', 68), '"ab'.str_repeat('x', 62).'…" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'the longest text kept whole' => ['ab'.str_repeat('x', 62), '"ab'.str_repeat('x', 62).'" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'the shortest text cut' => ['ab'.str_repeat('x', 63), '"ab'.str_repeat('x', 62).'…" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
    }

    #[DataProvider('refused')]
    public function testItRefusesWhatIsNotLineRanges(string $text, string $message): void
    {
        try {
            LineRanges::parse($text);
            self::fail('Refused');
        } catch (InvalidLineRanges $invalidLineRanges) {
            self::assertInstanceOf(HighlightException::class, $invalidLineRanges);
            self::assertSame($message, $invalidLineRanges->getMessage());
        }
    }

    public function testTheMostRangesAreRead(): void
    {
        self::assertCount(256, LineRanges::parse(implode(',', range(1, 512, 2)))->toArray());
    }

    public function testItTellsWhetherItHoldsALine(): void
    {
        $ranges = LineRanges::parse('2,4-6,9');

        self::assertSame(
            [false, false, true, false, true, true, true, false, false, true, false],
            array_map($ranges->contains(...), range(0, 10)),
        );
        self::assertFalse(LineRanges::none()->contains(1));
        self::assertTrue(LineRanges::none()->isEmpty());
    }

    public function testRangesAreGivenAsPairs(): void
    {
        self::assertSame([[1, 2], [5, 5]], LineRanges::of([5, 5], [1, 2])->toArray());
        self::assertSame([], LineRanges::of()->toArray());
    }

    /**
     * @return iterable<string, array{array{int, int}, string}>
     */
    public static function refusedPairs(): iterable
    {
        yield 'from 0' => [[0, 2], '"0-2" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'to 0' => [[1, 0], '"1-0" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".'];
        yield 'from too far' => [[1_000_001, 1_000_002], 'A line number is 1000000 at most.'];
        yield 'to too far' => [[1, 1_000_001], 'A line number is 1000000 at most.'];
        yield 'reversed' => [[4, 2], 'The range 4-2 ends before it starts.'];
    }

    /**
     * @param array{int, int} $pair
     */
    #[DataProvider('refusedPairs')]
    public function testItRefusesPairsOutOfBounds(array $pair, string $message): void
    {
        $this->expectException(InvalidLineRanges::class);
        $this->expectExceptionMessageIsOrContains($message);

        LineRanges::of($pair);
    }

    public function testItRefusesTooManyPairs(): void
    {
        $this->expectExceptionMessageIsOrContains('Line ranges hold 256 ranges at most.');

        LineRanges::of(...array_map(static fn (int $line): array => [$line, $line], range(1, 257)));
    }

    public function testTheMostPairsAreRead(): void
    {
        self::assertSame([[1, 256]], LineRanges::of(...array_map(static fn (int $line): array => [$line, $line], range(1, 256)))->toArray());
    }
}
