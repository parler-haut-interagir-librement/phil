<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use Phil\PagedMedia\Css\PageRule;
use Phil\PagedMedia\Css\PrinterMarks;
use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Pdf\PageSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageRule::class)]
#[CoversClass(PrinterMarks::class)]
final class PageRuleTest extends TestCase
{
    public function testASizeAloneLeavesTheRestToTheEngine(): void
    {
        self::assertSame('@page { size: 595pt 842pt }', new PageRule(PageSize::a4())->css());
    }

    public function testEveryDeclarationIsWrittenInAFixedOrder(): void
    {
        $rule = new PageRule(PageSize::ofMillimetres(148, 210), [36, 27.5], 8.5, PrinterMarks::CropAndCross);

        self::assertSame('@page { size: 419.53pt 595.28pt; margin: 36pt 27.5pt; bleed: 8.5pt; marks: crop cross }', $rule->css());
        self::assertSame([36.0, 27.5], $rule->margins());
    }

    public function testAZeroMarginIsWrittenWithoutAUnit(): void
    {
        self::assertSame('@page { size: 612pt 792pt; margin: 0 18pt 0 18pt }', new PageRule(PageSize::letter(), [0, 18, 0, 18])->css());
    }

    /**
     * @return iterable<string, array{PrinterMarks, string}>
     */
    public static function marks(): iterable
    {
        yield 'crop' => [PrinterMarks::Crop, 'crop'];
        yield 'cross' => [PrinterMarks::Cross, 'cross'];
        yield 'both' => [PrinterMarks::CropAndCross, 'crop cross'];
    }

    #[DataProvider('marks')]
    public function testTheMarksAreWritten(PrinterMarks $marks, string $css): void
    {
        self::assertSame('@page { size: 420pt 595pt; marks: '.$css.' }', new PageRule(PageSize::a5(), marks: $marks)->css());
    }

    public function testTheLimitsAreAccepted(): void
    {
        $rule = new PageRule(PageSize::of(100, 200), [0, 49.99], PageRule::MAXIMUM_BLEED);

        self::assertSame('@page { size: 100pt 200pt; margin: 0 49.99pt; bleed: 72pt }', $rule->css());
    }

    /**
     * @return iterable<string, array{list<float>|array<string, float>, float, string}>
     */
    public static function refused(): iterable
    {
        yield 'five margins' => [[1, 1, 1, 1, 1], 0, 'A page has one to four margins, as the CSS margin shorthand writes them.'];
        yield 'named margins' => [['top' => 1.0], 0, 'A page has one to four margins, as the CSS margin shorthand writes them.'];
        yield 'a negative margin' => [[-0.01], 0, 'A margin is at least 0 and under half the shorter side, 50 points; -0.01 is not.'];
        yield 'half the shorter side' => [[50], 0, 'A margin is at least 0 and under half the shorter side, 50 points; 50 is not.'];
        yield 'a negative bleed' => [[], -0.01, 'The bleed is between 0 and 72 points; -0.01 is not.'];
        yield 'a bleed over an inch' => [[], 72.01, 'The bleed is between 0 and 72 points; 72.01 is not.'];
    }

    /**
     * @param array<float> $margins
     */
    #[DataProvider('refused')]
    public function testAMeasureOutOfRangeIsRefused(array $margins, float $bleed, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PageRule(PageSize::of(100, 200), $margins, $bleed);
    }
}
