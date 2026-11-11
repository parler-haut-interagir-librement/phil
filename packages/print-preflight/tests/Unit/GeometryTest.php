<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Phil\PrintPreflight\Edge;
use Phil\PrintPreflight\Exception\InvalidDocument;
use Phil\PrintPreflight\Exception\PrintPreflightException;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Length::class)]
#[CoversClass(Size::class)]
#[CoversClass(Rectangle::class)]
#[CoversClass(Edge::class)]
#[CoversClass(InvalidDocument::class)]
final class GeometryTest extends TestCase
{
    public function testLengthsConvertBetweenUnits(): void
    {
        self::assertEqualsWithDelta(72.0, Length::mm(25.4)->points, 1e-9);
        self::assertEqualsWithDelta(144.0, Length::in(2)->points, 1e-9);
        self::assertEqualsWithDelta(72.0, Length::px(96)->points, 1e-9);
        self::assertSame(12.5, Length::pt(12.5)->points);
        self::assertEqualsWithDelta(25.4, Length::pt(72)->millimetres(), 1e-9);
        self::assertEqualsWithDelta(0.5, Length::pt(36)->inches(), 1e-9);
    }

    public function testShorterMeansShorterByMoreThanTheTolerance(): void
    {
        self::assertFalse(Length::pt(10)->isShorterThan(Length::pt(10.5)));
        self::assertTrue(Length::pt(9.9)->isShorterThan(Length::pt(10.5)));
        self::assertFalse(Length::pt(11)->isShorterThan(Length::pt(10)));
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function numbers(): iterable
    {
        yield 'whole' => [3.0, '3'];
        yield 'tens' => [10.0, '10'];
        yield 'one decimal' => [8.50394, '8.5'];
        yield 'rounded up to whole' => [2.96, '3'];
        yield 'negative' => [-1.26, '-1.3'];
        yield 'thousands without separator' => [1234.5, '1234.5'];
    }

    #[DataProvider('numbers')]
    public function testNumbersAreWrittenToOneDecimalWithoutTrailingZero(float $value, string $expected): void
    {
        self::assertSame($expected, Length::number($value));
    }

    public function testLengthsDescribeThemselvesInMillimetres(): void
    {
        self::assertSame('3 mm', Length::mm(3)->describe());
        self::assertSame('3.2 mm', Length::in(0.125)->describe());
        self::assertSame('0.25 pt', Length::pt(0.25)->describeInPoints());
        self::assertSame('0.1 pt', Length::pt(0.1)->describeInPoints());
        self::assertSame('0 pt', Length::pt(0)->describeInPoints());
        self::assertSame('2 pt', Length::pt(2.001)->describeInPoints());
        self::assertSame('10 pt', Length::pt(10)->describeInPoints());
        self::assertSame('0.28 pt', Length::mm(0.1)->describeInPoints());
    }

    public function testSizesAreBuiltInEachUnit(): void
    {
        self::assertEqualsWithDelta(612.0, Size::letter()->width->points, 1e-9);
        self::assertEqualsWithDelta(792.0, Size::letter()->height->points, 1e-9);
        self::assertSame('210 × 297 mm', Size::a4()->describe());
        self::assertSame('148 × 210 mm', Size::a5()->describe());
        self::assertSame('215.9 × 279.4 mm', Size::in(8.5, 11)->describe());
        self::assertSame(100.0, Size::pt(100, 200)->width->points);
        self::assertSame(200.0, Size::pt(100, 200)->height->points);
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function invalidSizes(): iterable
    {
        yield 'zero width' => [0, 297];
        yield 'zero height' => [210, 0];
        yield 'negative width' => [-1, 297];
    }

    #[DataProvider('invalidSizes')]
    public function testSizesMustBePositive(float $width, float $height): void
    {
        $this->expectException(InvalidDocument::class);

        Size::mm($width, $height);
    }

    public function testAnInvalidSizeSaysWhatWasGiven(): void
    {
        try {
            Size::mm(0, 297);
            self::fail('No exception.');
        } catch (PrintPreflightException $printPreflightException) {
            self::assertSame('A size must be positive; 0 × 297 mm given.', $printPreflightException->getMessage());
        }
    }

    public function testSizesMatchWithinTheTolerance(): void
    {
        $size = Size::pt(100, 100);

        self::assertTrue($size->matches(Size::pt(100.5, 100)));
        self::assertTrue($size->matches(Size::pt(100, 100.5)));
        self::assertTrue($size->matches(Size::pt(99.5, 99.5)));
        self::assertFalse($size->matches(Size::pt(100.6, 100)));
        self::assertFalse($size->matches(Size::pt(100, 100.6)));
        self::assertFalse($size->matches(Size::pt(99.4, 100)));
        self::assertFalse($size->matches(Size::pt(100, 99.4)));
        self::assertTrue(Size::a4()->matches(Size::px(794, 1123)));
        self::assertFalse(Size::a4()->matches(Size::mm(297, 210)));
    }

    public function testRectanglesGiveTheirEdges(): void
    {
        $rectangle = Rectangle::pt(10, 20, 30, 40);

        self::assertSame(10.0, $rectangle->left());
        self::assertSame(20.0, $rectangle->top());
        self::assertSame(40.0, $rectangle->right());
        self::assertSame(60.0, $rectangle->bottom());
        self::assertEqualsWithDelta(72.0, Rectangle::mm(25.4, 0, 0, 0)->left(), 1e-9);
        self::assertEqualsWithDelta(72.0, Rectangle::mm(0, 0, 25.4, 25.4)->bottom(), 1e-9);
        self::assertEqualsWithDelta(75.0, Rectangle::px(0, 100, 0, 0)->top(), 1e-9);
        self::assertEqualsWithDelta(150.0, Rectangle::px(100, 0, 100, 0)->right(), 1e-9);
        self::assertEqualsWithDelta(216.0, Rectangle::in(1, 2, 3, 4)->top() + Rectangle::in(1, 0, 0, 0)->left(), 1e-9);
        self::assertEqualsWithDelta(504.0, Rectangle::in(1, 2, 3, 4)->right() + Rectangle::in(0, 0, 0, 3)->bottom(), 1e-9);
    }

    public function testRectanglesMayBeFlatButNotNegative(): void
    {
        self::assertSame(0.0, Rectangle::pt(0, 0, 0, 0)->width->points);

        try {
            Rectangle::pt(0, 0, -1, 10);
            self::fail('No exception.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('A rectangle cannot have a negative width or height.', $invalidDocument->getMessage());
        }

        $this->expectException(InvalidDocument::class);

        Rectangle::pt(0, 0, 10, -1);
    }

    public function testPdfCoordinatesAreTurnedToTheTrimBox(): void
    {
        $rectangle = Rectangle::fromPdf(36, 700, 100, 50, 842);

        self::assertSame(36.0, $rectangle->left());
        self::assertSame(92.0, $rectangle->top());
        self::assertSame(100.0, $rectangle->width->points);
        self::assertSame(50.0, $rectangle->height->points);

        $bled = Rectangle::fromPdf(36, 700, 100, 50, 860, Length::pt(9));

        self::assertSame(27.0, $bled->left());
        self::assertSame(101.0, $bled->top());
    }

    public function testEdgesAreListedForAMessage(): void
    {
        self::assertSame('top, left', Edge::describe([Edge::Top, Edge::Left]));
        self::assertSame('', Edge::describe([]));
    }
}
