<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Layout;

use Phil\BrailleExport\Book\Page;
use Phil\BrailleExport\Book\Volume;
use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Contract\Block;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\Table;
use Phil\BrailleExport\Layout\Internal\Entry;
use Phil\BrailleExport\Layout\Internal\PageNumbers;
use Phil\BrailleExport\Layout\Internal\PageSink;
use Phil\BrailleExport\Layout\Internal\Prepared;
use Phil\BrailleExport\Layout\Internal\Translated;
use Phil\BrailleExport\Layout\PageFormat;
use Phil\BrailleExport\Tests\Unit\Fixture\Ascii;
use Phil\BrailleExport\Tests\Unit\Fixture\LetterTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrailleExporter::class)]
#[CoversClass(Prepared::class)]
#[CoversClass(Entry::class)]
#[CoversClass(PageNumbers::class)]
#[CoversClass(PageSink::class)]
#[CoversClass(Translated::class)]
final class PageBoundariesTest extends TestCase
{
    /**
     * @return iterable<string, array{list<Block>, int, list<list<string>>}>
     */
    public static function headings(): iterable
    {
        $three = new Paragraph("a\nb\nc");
        $text = new Paragraph('z');

        yield 'a centred heading needs a line more than its own, a blank line on each side' => [
            [$three, new Heading('title', 1), $text],
            6,
            [['  A', 'B', 'C'], ['       TITLE', '', '  Z']],
        ];

        yield 'a heading of another level needs no blank line after it' => [
            [$three, new Heading('part', 2), $text],
            6,
            [['  A', 'B', 'C', '', '    PART', '  Z']],
        ];

        yield 'a centred heading takes lines as it is cut in the width of its centre' => [
            [$three, new Heading('aaaaaaaa bbbbbbbb', 1), $text],
            7,
            [['  A', 'B', 'C'], ['      AAAAAAAA', '      BBBBBBBB', '', '  Z']],
        ];

        yield 'a centred heading of one line less than its width stays' => [
            [$three, new Heading('aaaaaa bbbbb', 1), $text],
            7,
            [['  A', 'B', 'C', '', '    AAAAAA BBBBB', '', '  Z']],
        ];

        yield 'a heading of another level is cut in the width left by its indentation' => [
            [$three, new Heading('aaaaaaa bbbbbbb', 2), $text],
            6,
            [['  A', 'B', 'C', '', '    AAAAAAA BBBBBBB', '  Z']],
        ];

        yield 'a heading of another level takes lines as it is cut in that width' => [
            [$three, new Heading('aaaaaaaaa bbbbbbbbbb', 2), $text],
            6,
            [['  A', 'B', 'C'], ['    AAAAAAAAA', '    BBBBBBBBBB', '  Z']],
        ];
    }

    /**
     * @param list<Block>        $blocks
     * @param list<list<string>> $expected
     */
    #[DataProvider('headings')]
    public function testAHeadingStaysOnThePageThatHoldsItsLines(array $blocks, int $lines, array $expected): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(20, $lines, braillePageNumbers: false))->layout(new Document($blocks));

        self::assertSame($expected, array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
    }

    public function testTheVolumesAreFilledOneAfterTheOther(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false, pagesPerVolume: 2))->layout(new Document([
            new Paragraph("a\nb\nc\nd\ne\nf\ng\nh\ni\nj\nk\nl\nm\nn\no"),
        ]));

        self::assertSame([2, 2, 1], array_map(static fn (Volume $volume): int => \count($volume->pages()), $book->volumes));
    }

    public function testTheCaptionOfATableRunsOverInCellOne(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(14, 5, braillePageNumbers: false))->layout(new Document([
            new Table([], false, 'one two three four five six'),
        ]));

        self::assertSame(['  ONE TWO', 'THREE FOUR', 'FIVE SIX'], Ascii::rows($book->pages()[0]->rows));
    }

    public function testTheIndentationOfEachKindOfBlock(): void
    {
        $prepared = Prepared::of(new Document([
            new Heading('one', 1),
            new Heading('two', 2),
            new Heading('three', 3),
            new Table([], false, 'caption'),
        ]));

        self::assertEquals([
            new Entry(Entry::HEADING, [0], 0, 0, 1),
            new Entry(Entry::HEADING, [1], 4, 4, 2),
            new Entry(Entry::HEADING, [2], 6, 6, 3),
            new Entry(Entry::TEXT, [3], 2, 0),
        ], $prepared->entries);
        self::assertSame(['one', 'two', 'three', 'caption'], $prepared->texts);
    }

    public function testAnEntryStartsAtCellOneOfLevelZeroWithoutRawText(): void
    {
        $entry = new Entry(Entry::PAGE_BREAK);

        self::assertSame([], $entry->texts);
        self::assertSame(0, $entry->indent);
        self::assertSame(0, $entry->runover);
        self::assertSame(0, $entry->level);
        self::assertSame('', $entry->raw);
    }

    public function testNothingIsSentToTheTranslatorWithoutTexts(): void
    {
        $translator = new LetterTranslator();

        self::assertSame([], Translated::texts($translator, [], 'en', Contraction::Uncontracted));
        self::assertSame([], $translator->batches);
    }

    public function testThePageNumbersAreKeptWhenALaterRunIsAsked(): void
    {
        $translator = new LetterTranslator();
        $numbers = new PageNumbers($translator, 'en', Contraction::Uncontracted);

        self::assertSame('A', Ascii::of($numbers->cells(1)));
        self::assertSame('FE', Ascii::of($numbers->cells(65)));
        self::assertSame('B', Ascii::of($numbers->cells(2)));
        self::assertCount(2, $translator->batches);
    }
}
