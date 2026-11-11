<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit;

use Phil\Highlight\Exception\InvalidLineRanges;
use Phil\Highlight\LineMark;
use Phil\Highlight\LineOptions;
use Phil\Highlight\LineRanges;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineOptions::class)]
#[CoversClass(LineMark::class)]
final class LineOptionsTest extends TestCase
{
    public function testByDefaultNothingIsNumberedMarkedOrRead(): void
    {
        $options = new LineOptions();

        self::assertNull($options->firstNumber);
        self::assertTrue($options->highlighted->isEmpty());
        self::assertFalse($options->annotations);
    }

    public function testEachOptionIsChangedAlone(): void
    {
        $ranges = LineRanges::parse('2-3');
        $options = LineOptions::numbered()->withHighlighted($ranges)->withAnnotations();

        self::assertSame(1, $options->firstNumber);
        self::assertSame($ranges, $options->highlighted);
        self::assertTrue($options->annotations);

        $renumbered = $options->withFirstNumber(10);
        self::assertSame(10, $renumbered->firstNumber);
        self::assertSame($ranges, $renumbered->highlighted);
        self::assertTrue($renumbered->annotations);

        $unnumbered = $options->withFirstNumber(null)->withAnnotations(false);
        self::assertNull($unnumbered->firstNumber);
        self::assertSame($ranges, $unnumbered->highlighted);
        self::assertFalse($unnumbered->annotations);

        self::assertSame(7, LineOptions::numbered(7)->firstNumber);
        self::assertSame(1, $options->firstNumber);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function firstNumbers(): iterable
    {
        yield 'zero' => [0];
        yield 'the greatest' => [1_000_000];
    }

    #[DataProvider('firstNumbers')]
    public function testTheFirstNumberIsWithinBounds(int $number): void
    {
        self::assertSame($number, new LineOptions($number)->firstNumber);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedNumbers(): iterable
    {
        yield 'negative' => [-1];
        yield 'too large' => [1_000_001];
    }

    #[DataProvider('refusedNumbers')]
    public function testAFirstNumberOutOfBoundsIsRefused(int $number): void
    {
        $this->expectException(InvalidLineRanges::class);
        $this->expectExceptionMessageIsOrContains('The first line number is between 0 and 1000000.');

        new LineOptions($number);
    }

    public function testEachMarkHasItsClassAndItsAnnotation(): void
    {
        self::assertSame(['highlighted', 'inserted', 'deleted'], array_map(static fn (LineMark $mark): string => $mark->value, LineMark::cases()));
        self::assertSame('hl-line-inserted', LineMark::Inserted->className());
        self::assertSame(LineMark::Inserted, LineMark::fromAnnotation('++'));
        self::assertSame(LineMark::Deleted, LineMark::fromAnnotation('--'));
        self::assertSame(LineMark::Highlighted, LineMark::fromAnnotation('highlight'));
        self::assertNull(LineMark::fromAnnotation('focus'));
    }
}
