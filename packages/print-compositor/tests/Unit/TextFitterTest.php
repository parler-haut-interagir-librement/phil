<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit;

use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Exception\MissingGlyphs;
use Phil\PrintCompositor\Exception\TextDoesNotFit;
use Phil\PrintCompositor\FittedLine;
use Phil\PrintCompositor\FittedText;
use Phil\PrintCompositor\Internal\Gd;
use Phil\PrintCompositor\Tests\Unit\Fixture\Fonts;
use Phil\PrintCompositor\TextFitter;
use Phil\PrintCompositor\TextItem;
use Phil\PrintCompositor\TypeFace;
use Phil\PrintCompositor\Units;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * At 72 dpi a point is a pixel: at 100 pt the em of the test font is 100 pixels, a capital 50 wide and 70 high, 5 from
 * its origin.
 */
#[CoversClass(TextFitter::class)]
#[CoversClass(FittedText::class)]
#[CoversClass(FittedLine::class)]
#[CoversClass(Gd::class)]
#[CoversClass(MissingGlyphs::class)]
#[CoversClass(TextDoesNotFit::class)]
#[CoversClass(TextItem::class)]
#[CoversClass(TypeFace::class)]
#[CoversClass(Box::class)]
#[CoversClass(Units::class)]
final class TextFitterTest extends TestCase
{
    public function testATextTakesItsLargestSizeThatFits(): void
    {
        $fitted = $this->fit('H', $this->box(0, 0, 100, 70), 100);

        self::assertSame(100.0, $fitted->size);
        self::assertSame([50, 70, 72], [$fitted->width, $fitted->height, $fitted->dpi]);
        self::assertEquals([new FittedLine('H', 70, 5, 50)], $fitted->lines);
    }

    public function testASizeIsTriedByHalfPointsFromTheMaximum(): void
    {
        self::assertSame(98.5, $this->fit('H', $this->box(0, 0, 100, 69), 100)->size, 'At 99 pt a capital is 69.3 pixels high, inked over 70 rows.');
        self::assertSame(100.25, $this->fit('W', $this->box(0, 0, 90, 90), 100.25)->size);
        self::assertSame(94.75, $this->fit('W', $this->box(0, 0, 85, 90), 100.25)->size, 'The steps start from the maximum: 100.25, 99.75… 94.75.');
    }

    public function testTheMinimumIsTriedLastEvenOffTheSteps(): void
    {
        $item = new TextItem('W', TypeFace::fromFile(Fonts::trueType()), new Box(0, 0, 296 * 25.4 / 2400, 10), maxSize: 10, minSize: 9.8);

        self::assertSame(9.8, new TextFitter()->fit($item, 2400)->size, 'At 2400 dpi a "W" is 300 pixels wide at 10 pt, 294 at 9.8 pt.');
        self::assertSame(12.0, $this->fit('H', $this->box(0, 0, 10, 10), 12, 12)->size, 'A single size is tried once.');
    }

    public function testTheMaximumIsTriedFirstEvenWhenTheMinimumIsOffTheSteps(): void
    {
        self::assertSame(10.0, $this->fit('H', self::box(0, 0, 100, 100), 10, 9.8)->size);
    }

    public function testATextThatDrawsNothingFitsWithoutInk(): void
    {
        $fitted = $this->fit("\u{A0}", self::box(0, 0, 10, 10), 20);

        self::assertSame(20.0, $fitted->size);
        self::assertSame([0, 0], [$fitted->width, $fitted->height]);
        self::assertEquals([new FittedLine("\u{A0}", 0, 0, 0)], $fitted->lines, 'The no-break space is covered and draws nothing: its line is at the top.');
    }

    public function testLinesBreakWhereTheBoundingBoxOfFreeTypeGoesPastTheBox(): void
    {
        $bounds = imagettfbbox(75, 0, Fonts::trueType(), 'H H');
        \assert(false !== $bounds && \is_int($bounds[0]) && \is_int($bounds[2]) && \is_int($bounds[4]) && \is_int($bounds[6]));
        $width = max($bounds[2], $bounds[4]) - min($bounds[0], $bounds[6]);

        self::assertSame(['H H'], $this->fit('H H', self::box(0, 0, $width, 300), 100, 100)->lineTexts(), 'A line as wide as the box stays whole.');
        self::assertSame(['H', 'H'], $this->fit('H H', self::box(0, 0, $width - 1, 300), 100, 100)->lineTexts());
    }

    public function testTheDistanceBetweenBaselinesIsRoundedToThePixel(): void
    {
        self::assertSame(190, $this->fit("H\nH", self::box(0, 0, 100, 300), 100, wrap: false, spacing: 1.204)->lines[1]->baseline, '120.4 pixels round to 120.');
        self::assertSame(191, $this->fit("H\nH", self::box(0, 0, 100, 300), 100, wrap: false, spacing: 1.206)->lines[1]->baseline, '120.6 pixels round to 121.');
    }

    public function testATextThatDoesNotFitAtItsMinimumIsRefused(): void
    {
        $this->expectException(TextDoesNotFit::class);
        $this->expectExceptionMessageIsOrContains('The text "Hello" does not fit its 1 × 2 mm box, even at 6.5 pt.');

        $this->fit('Hello', new Box(0, 0, 1, 2), 20, 6.5);
    }

    public function testInkThatGoesBelowTheBaselineCounts(): void
    {
        $fitted = $this->fit('Hg', $this->box(0, 0, 200, 200), 100);

        self::assertSame([100, 90], [$fitted->width, $fitted->height], 'The capital rises 70 pixels and the "g" goes 20 below.');
        self::assertEquals([new FittedLine('Hg', 70, 5, 100)], $fitted->lines);
    }

    public function testATextWrapsAtSpaces(): void
    {
        $fitted = $this->fit('Hi Hi', $this->box(0, 0, 100, 200), 100);

        self::assertSame(100.0, $fitted->size, 'FreeType gives each line five pixels more than its ink: the ink decides.');
        self::assertSame(['Hi', 'Hi'], $fitted->lineTexts());
        self::assertSame([100, 190], [$fitted->width, $fitted->height], 'The second baseline is 120 pixels down, at a line spacing of 1.2.');
        self::assertEquals([new FittedLine('Hi', 70, 5, 100), new FittedLine('Hi', 190, 5, 100)], $fitted->lines);
    }

    public function testAWrappedLineTakesAsManyWordsAsFit(): void
    {
        self::assertSame(['H H', 'H'], $this->fit('H  H H', $this->box(0, 0, 140, 300), 100)->lineTexts(), 'Spaces in a row count as one.');
        self::assertSame(['Hiiiiii', 'H'], $this->fit('Hiiiiii H', $this->box(0, 0, 100, 300), 30)->lineTexts(), 'A word is never broken.');
    }

    public function testATextWithoutWrappingKeepsItsLines(): void
    {
        $fitted = $this->fit('Hi Hi', $this->box(0, 0, 100, 200), 100, wrap: false);

        self::assertSame(42.5, $fitted->size);
        self::assertEquals([new FittedLine('Hi Hi', 29, 2, 100)], $fitted->lines);
    }

    public function testALineBreakStartsALineAndAnEmptyLineKeepsItsPlace(): void
    {
        $fitted = $this->fit("H\n\nH", $this->box(0, 0, 100, 300), 100, wrap: false, spacing: 1.0);

        self::assertSame(270, $fitted->height);
        self::assertEquals([new FittedLine('H', 70, 5, 50), new FittedLine('', 170, 0, 0), new FittedLine('H', 270, 5, 50)], $fitted->lines);
    }

    public function testALineOfSpacesDrawsNothing(): void
    {
        $fitted = $this->fit("  \nH", $this->box(0, 0, 100, 300), 100, wrap: false, spacing: 1.0);

        self::assertEquals([new FittedLine('  ', -30, 0, 0), new FittedLine('H', 70, 5, 50)], $fitted->lines, 'The top of the text is the top of its ink.');
        self::assertSame(70, $fitted->height);
    }

    public function testTheTopOfTheTextIsTheHighestInkOfAnyLine(): void
    {
        $fitted = $this->fit(".\nH", $this->box(0, 0, 100, 300), 100, wrap: false, spacing: 0.5);

        self::assertEquals([new FittedLine('.', 20, 5, 10), new FittedLine('H', 70, 5, 50)], $fitted->lines, 'The capital, 50 pixels down, rises 20 pixels above the full stop.');
        self::assertSame(70, $fitted->height);
    }

    public function testCharactersTheFontDoesNotCoverAreRefused(): void
    {
        $this->expectException(MissingGlyphs::class);
        $this->expectExceptionMessageIsOrContains('The font "Block Test" has no glyph for "€" (U+20AC), "~" (U+007E) in the text "5 € ~ 6 ~".');

        $this->fit('5 € ~ 6 ~', $this->box(0, 0, 100, 100), 100);
    }

    public function testTheResolutionIsCheckedAnd300ByDefault(): void
    {
        $item = new TextItem('H', TypeFace::fromFile(Fonts::trueType()), new Box(0, 0, 100, 100), maxSize: 10, minSize: 10);

        self::assertSame(300, new TextFitter()->fit($item)->dpi);
        self::assertSame(21, new TextFitter()->fit($item)->width, 'At 300 dpi, 10 pt is an em of 41.7 pixels.');

        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('A resolution goes from 1 to 2400 dpi; 0 given.');

        new TextFitter()->fit($item, 0);
    }

    public function testGdSizesAreInPointsAt96Dpi(): void
    {
        self::assertSame(75.0, TextFitter::gdSize(100, 72));
        self::assertSame(312.5, TextFitter::gdSize(100, 300));
    }

    /**
     * A box given in pixels at 72 dpi.
     */
    private function box(float $x, float $y, float $width, float $height): Box
    {
        $millimetres = 25.4 / 72;

        return new Box($x * $millimetres, $y * $millimetres, $width * $millimetres, $height * $millimetres);
    }

    private function fit(string $text, Box $box, float $max, float $min = 6.0, bool $wrap = true, float $spacing = 1.2): FittedText
    {
        $item = new TextItem($text, TypeFace::fromFile(Fonts::trueType()), $box, maxSize: $max, minSize: $min, wrap: $wrap, lineSpacing: $spacing);

        return new TextFitter()->fit($item, 72);
    }
}
