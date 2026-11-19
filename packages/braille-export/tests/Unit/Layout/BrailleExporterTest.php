<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Layout;

use Phil\BrailleExport\Book\Book;
use Phil\BrailleExport\Book\Page;
use Phil\BrailleExport\Book\Section;
use Phil\BrailleExport\Book\Volume;
use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Contract\Block;
use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\ListBlock;
use Phil\BrailleExport\Document\ListItem;
use Phil\BrailleExport\Document\PageBreak;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\PrintPage;
use Phil\BrailleExport\Document\SectionBreak;
use Phil\BrailleExport\Document\Table;
use Phil\BrailleExport\Exception\DocumentTooLong;
use Phil\BrailleExport\Exception\InvalidBraille;
use Phil\BrailleExport\Layout\Internal\Entry;
use Phil\BrailleExport\Layout\Internal\Flow;
use Phil\BrailleExport\Layout\Internal\PageNumbers;
use Phil\BrailleExport\Layout\Internal\PageSink;
use Phil\BrailleExport\Layout\Internal\Prepared;
use Phil\BrailleExport\Layout\Internal\Translated;
use Phil\BrailleExport\Layout\PageFormat;
use Phil\BrailleExport\Tests\Unit\Fixture\Ascii;
use Phil\BrailleExport\Tests\Unit\Fixture\LetterTranslator;
use Phil\BrailleExport\Translation\Braille;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrailleExporter::class)]
#[CoversClass(Prepared::class)]
#[CoversClass(Entry::class)]
#[CoversClass(PageSink::class)]
#[CoversClass(PageNumbers::class)]
#[CoversClass(Translated::class)]
#[CoversClass(Flow::class)]
#[CoversClass(Book::class)]
#[CoversClass(Volume::class)]
#[CoversClass(Section::class)]
#[CoversClass(Page::class)]
#[CoversClass(DocumentTooLong::class)]
#[CoversClass(InvalidBraille::class)]
final class BrailleExporterTest extends TestCase
{
    public function testAParagraphStartsInCellThreeAndRunsOverInCellOne(): void
    {
        $rows = $this->rows([new Paragraph('one two three four five six')], new PageFormat(14, 5, braillePageNumbers: false));

        self::assertSame(['  ONE TWO', 'THREE FOUR', 'FIVE SIX'], $rows);
    }

    public function testALineBreakStartsANewLineInCellOne(): void
    {
        $rows = $this->rows([new Paragraph("one\r\ntwo\n\n  three  \rfour")], new PageFormat(14, 5, braillePageNumbers: false));

        self::assertSame(['  ONE', 'TWO', 'THREE', 'FOUR'], $rows);
    }

    public function testWhiteSpaceAndControlCharactersAreFolded(): void
    {
        $translator = new LetterTranslator();
        new BrailleExporter($translator)->layout(new Document([new Paragraph("a\t\u{A0}b\u{200B}c\x01d"), new Paragraph(" \n\t ")]));

        self::assertSame([['a b c d'], array_map(strval(...), range(1, 64))], $translator->batches);
    }

    public function testAWordLongerThanALineIsCutWithAHyphen(): void
    {
        $rows = $this->rows([new Paragraph('ab abcdefghijklmnopqrstuvwxyz')], new PageFormat(12, 5, braillePageNumbers: false));

        self::assertSame(['  AB', 'ABCDEFGHIJK-', 'LMNOPQRSTUV-', 'WXYZ'], $rows);
    }

    public function testACutNeverFollowsACellThatAnnouncesTheNext(): void
    {
        $rows = $this->rows([new Paragraph('abcdefghij#a ab abcdefghij,ab')], new PageFormat(12, 5, braillePageNumbers: false));

        self::assertSame(['  ABCDEFGHI-', 'J#A AB', 'ABCDEFGHIJ-', ',AB'], $rows);
    }

    public function testACutOfAnnouncingCellsOnlyTakesTheWholeRoom(): void
    {
        $rows = $this->rows([new Paragraph('###############')], new PageFormat(12, 5, braillePageNumbers: false));

        self::assertSame(['  #########-', '######'], $rows);
    }

    public function testAWordIsCutAfterItsOwnHyphenWithoutAddingOne(): void
    {
        $rows = $this->rows([new Paragraph('ab cd-ef-ghij klm')], new PageFormat(12, 5, braillePageNumbers: false));

        self::assertSame(['  AB CD-EF-', 'GHIJ KLM'], $rows);
    }

    public function testAHyphenAtTheStartOrTheEndOfAWordIsNoPlaceToCut(): void
    {
        $rows = $this->rows([new Paragraph('abcdef -abcdef abcdefgh-')], new PageFormat(12, 5, braillePageNumbers: false));

        self::assertSame(['  ABCDEF', '-ABCDEF', 'ABCDEFGH-'], $rows);
    }

    public function testALongHyphenatedWordIsCutAtItsLastHyphenThatFits(): void
    {
        $rows = $this->rows([new Paragraph('aa-bb-cc-dd-ee-ff')], new PageFormat(12, 5, braillePageNumbers: false));

        self::assertSame(['  AA-BB-CC-', 'DD-EE-FF'], $rows);
    }

    public function testHeadingsOfEachLevel(): void
    {
        $rows = $this->rows([
            new Heading('title', 1),
            new Paragraph('text'),
            new Heading('part', 2),
            new Paragraph('text'),
            new Heading('sub', 3),
            new Heading('deep', 6),
        ], new PageFormat(20, 20, braillePageNumbers: false));

        self::assertSame(['       TITLE', '', '  TEXT', '', '    PART', '  TEXT', '', '      SUB', '', '      DEEP'], $rows);
    }

    public function testACentredHeadingRunsOverCentred(): void
    {
        $rows = $this->rows([new Heading('one two three four')], new PageFormat(16, 10, braillePageNumbers: false));

        self::assertSame(['    ONE TWO', '   THREE FOUR', ''], $rows);
    }

    public function testAHeadingGoesToTheNextPageWithTheLineThatFollowsIt(): void
    {
        $format = new PageFormat(20, 3, braillePageNumbers: false);
        $book = new BrailleExporter(new LetterTranslator(), $format)->layout(new Document([
            new Paragraph('one'),
            new Heading('part', 2),
            new Paragraph('two'),
        ]));

        self::assertSame([['  ONE'], ['    PART', '  TWO']], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
    }

    public function testAHeadingThatFitsWithItsLineStaysOnThePage(): void
    {
        $format = new PageFormat(20, 5, braillePageNumbers: false);
        $book = new BrailleExporter(new LetterTranslator(), $format)->layout(new Document([
            new Paragraph('one'),
            new Heading('title'),
            new Paragraph('two'),
        ]));

        self::assertSame([['  ONE', '', '       TITLE', '', '  TWO']], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
    }

    public function testAHeadingAtTheTopOfAPageNeedsNoBlankLineBefore(): void
    {
        $format = new PageFormat(20, 3, braillePageNumbers: false);
        $book = new BrailleExporter(new LetterTranslator(), $format)->layout(new Document([new Heading('title'), new Paragraph('one')]));

        self::assertSame([['       TITLE', '', '  ONE']], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
    }

    public function testAnEmptyHeadingIsLeftOut(): void
    {
        self::assertSame(['  ONE'], $this->rows([new Heading(" \n "), new Paragraph('one')], new PageFormat(20, 5, braillePageNumbers: false)));
    }

    public function testAHeadingWithoutBrailleIsLeftOut(): void
    {
        $translator = self::createStub(Translator::class);
        $translator->method('translate')->willReturn([Braille::BLANK, Ascii::braille('A')]);

        $book = new BrailleExporter($translator, new PageFormat(12, 3, braillePageNumbers: false))->layout(new Document([new Heading('x'), new Paragraph('y')]));

        self::assertSame(['  A'], Ascii::rows($book->pages()[0]->rows));
    }

    public function testListsAreIndentedByLevelAndNumbered(): void
    {
        $rows = $this->rows([
            new ListBlock([
                new ListItem('first item here', new ListBlock([new ListItem('inner item text')])),
                new ListItem('second'),
            ], true, 3),
            new ListBlock([new ListItem('plain')]),
        ], new PageFormat(14, 20, braillePageNumbers: false));

        self::assertSame(['C. FIRST ITEM', '  HERE', '  INNER ITEM', '    TEXT', 'D. SECOND', 'PLAIN'], $rows);
    }

    public function testDeepListsStopMovingIn(): void
    {
        $list = new ListBlock([new ListItem('x')]);

        for ($depth = 0; $depth < 10; ++$depth) {
            $list = new ListBlock([new ListItem('x', $list)]);
        }

        $rows = $this->rows([$list], new PageFormat(40, 20, braillePageNumbers: false));

        self::assertSame(str_repeat(' ', 16).'X', $rows[10]);
        self::assertSame(str_repeat(' ', 14).'X', $rows[7]);
    }

    public function testATableIsLinearizedRowByRow(): void
    {
        $rows = $this->rows([
            new Table([['name', 'age', ''], ['ann', '12', 'x'], ['bob', '', 'y z']], true, 'people'),
        ], new PageFormat(20, 20, braillePageNumbers: false));

        self::assertSame(['  PEOPLE', 'NAME: ANN; AGE: AB;', '  X', 'NAME: BOB; Y Z'], $rows);
    }

    public function testATableWithoutHeadings(): void
    {
        $rows = $this->rows([new Table([['a', 'b'], ['c']], false), new Table([['d', 'e']])], new PageFormat(20, 20, braillePageNumbers: false));

        self::assertSame(['A; B', 'C', 'D; E'], $rows);
    }

    public function testAFrenchTableSpacesItsColons(): void
    {
        $translator = new LetterTranslator();
        new BrailleExporter($translator)->layout(new Document([new Table([['nom'], ['ann']])], 'FR-ca'));

        self::assertSame('nom : ann', $translator->batches[0][0]);
    }

    public function testThePageNumberEndsTheLastLine(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3))->layout(new Document([
            new Paragraph('aaaa bbbb cccc dddd eeee'),
        ]));

        self::assertSame([
            ['  AAAA BBBB', 'CCCC DDDD', 'EEEE       A'],
        ], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
    }

    public function testTheLastLineLeavesRoomForThePageNumber(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3))->layout(new Document([
            new Paragraph('aaaa bbbb cccc dddd eeeeeeee ff'),
        ]));

        self::assertSame([
            ['  AAAA BBBB', 'CCCC DDDD', 'EEEEEEEE   A'],
            ['FF', '', '           B'],
        ], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
        self::assertSame([1, 2], array_map(static fn (Page $page): int => $page->number, $book->pages()));
    }

    public function testPageNumbersAreAskedOncePerRunOfPages(): void
    {
        $translator = new LetterTranslator();
        $book = new BrailleExporter($translator, new PageFormat(12, 3))->layout(new Document(array_fill(0, 70, new PageBreak()) + [new Paragraph('a')]));
        self::assertCount(1, $book->pages());

        $blocks = [];

        for ($page = 0; $page < 66; ++$page) {
            $blocks[] = new Paragraph('a');
            $blocks[] = new PageBreak();
        }

        $translator = new LetterTranslator();
        $book = new BrailleExporter($translator, new PageFormat(12, 3))->layout(new Document($blocks));

        self::assertCount(66, $book->pages());
        self::assertCount(3, $translator->batches);
        self::assertSame('1', $translator->batches[1][0]);
        self::assertSame('64', $translator->batches[1][63]);
        self::assertSame('65', $translator->batches[2][0]);
        self::assertSame('          FE', Ascii::of($book->pages()[64]->rows[2]));
    }

    public function testAnEmptyDocumentIsOnePage(): void
    {
        $translator = new LetterTranslator();
        $book = new BrailleExporter($translator, new PageFormat(12, 3))->layout(new Document([new PageBreak()]));

        self::assertSame([['', '', '           A']], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));

        $book = new BrailleExporter($translator, new PageFormat(12, 3, braillePageNumbers: false))->layout(new Document([]));
        self::assertSame([[]], array_map(static fn (Page $page): array => $page->rows, $book->pages()));
    }

    public function testAPageBreakStartsANewPageButNeverAnEmptyOne(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 5, braillePageNumbers: false))->layout(new Document([
            new PageBreak(),
            new Paragraph('one'),
            new PageBreak(),
            new PageBreak(),
            new Paragraph('two'),
        ]));

        self::assertSame([['  ONE'], ['  TWO']], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
    }

    public function testPrintPagesOnTheFirstLineAndAsALineWithinAPage(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(14, 4))->layout(new Document([
            new PrintPage('1'),
            new Paragraph('aaaa bbbb cccc'),
            new PrintPage(' 2 '),
            new Paragraph('dddd'),
            new PrintPage('3'),
            new Paragraph('eeee'),
            new PrintPage("\n"),
        ]));

        self::assertSame([
            ['  AAAA       A', 'BBBB CCCC', '------------ B', '  DDDD       A'],
            ['  EEEE       C', '', '', '             B'],
        ], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
        self::assertSame(['1', '3'], array_map(static fn (Page $page): ?string => $page->printPage, $book->pages()));
    }

    public function testAPrintPageThatWouldTakeTheLastLineGoesToTheNextPage(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(14, 3, braillePageNumbers: false))->layout(new Document([
            new Paragraph('one'),
            new Paragraph('two'),
            new PrintPage('5'),
            new Paragraph('three'),
        ]));

        self::assertSame([['  ONE', '  TWO'], ['  THREE      E']], array_map(static fn (Page $page): array => Ascii::rows($page->rows), $book->pages()));
    }

    public function testALongPrintPageNumberIsCut(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false))->layout(new Document([
            new PrintPage('abcdefgh'),
            new Paragraph('x'),
        ]));

        self::assertSame(['  X     ABCD'], Ascii::rows($book->pages()[0]->rows));
    }

    public function testPrintPagesCanBeLeftOut(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false, printPageNumbers: false))->layout(new Document([
            new PrintPage('1'),
            new Paragraph('x'),
            new PrintPage('2'),
            new Paragraph('y'),
        ]));

        self::assertSame(['  X', '  Y'], Ascii::rows($book->pages()[0]->rows));
        self::assertNull($book->pages()[0]->printPage);
    }

    public function testSectionsAndVolumes(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false, pagesPerVolume: 2))->layout(new Document([
            new SectionBreak(),
            new Paragraph('a'),
            new PageBreak(),
            new Paragraph('b'),
            new SectionBreak(),
            new SectionBreak(),
            new Paragraph('c'),
            new Paragraph('d'),
            new Paragraph('e'),
        ], 'en', 'Book'));

        $shape = array_map(static fn (Volume $volume): array => array_map(static fn (Section $section): array => array_map(static fn (Page $page): int => $page->number, $section->pages), $volume->sections), $book->volumes);

        self::assertSame([[[1, 2]], [[3]]], $shape);
        self::assertSame('Book', $book->title);
        self::assertSame('en', $book->language);

        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false, pagesPerVolume: 3))->layout(new Document([
            new Paragraph('a'),
            new SectionBreak(),
            new Paragraph('b'),
            new Paragraph('c'),
            new Paragraph('d'),
            new Paragraph('e'),
        ]));
        $shape = array_map(static fn (Volume $volume): array => array_map(static fn (Section $section): array => array_map(static fn (Page $page): int => $page->number, $section->pages), $volume->sections), $book->volumes);

        self::assertSame([[[1], [2, 3]]], $shape);

        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false, pagesPerVolume: 2))->layout(new Document([
            new Paragraph('a'),
            new SectionBreak(),
            new Paragraph('b'),
            new Paragraph('c'),
            new Paragraph('d'),
            new Paragraph('e'),
        ]));
        $shape = array_map(static fn (Volume $volume): array => array_map(static fn (Section $section): array => array_map(static fn (Page $page): int => $page->number, $section->pages), $volume->sections), $book->volumes);

        self::assertSame([[[1], [2]], [[3]]], $shape);
    }

    public function testAllPagesInOneVolumeByDefault(): void
    {
        $book = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false))->layout(new Document([
            new Paragraph('a'), new PageBreak(), new Paragraph('b'), new PageBreak(), new Paragraph('c'),
        ]));

        self::assertCount(1, $book->volumes);
        self::assertCount(3, $book->volumes[0]->pages());
        self::assertSame($book->format->cellsPerLine, 12);
    }

    public function testTheNumberOfPagesIsBounded(): void
    {
        $exporter = new BrailleExporter(new LetterTranslator(), new PageFormat(12, 3, braillePageNumbers: false, maxPages: 2));

        self::assertCount(2, $exporter->layout(new Document([new Paragraph('a'), new PageBreak(), new Paragraph('b')]))->pages());

        $this->expectException(DocumentTooLong::class);
        $this->expectExceptionMessageIsOrContains('The document fills more than the 2 braille pages allowed.');

        $exporter->layout(new Document([new Paragraph('a'), new PageBreak(), new Paragraph('b'), new PageBreak(), new Paragraph('c')]));
    }

    public function testTheWholeDocumentGoesToTheTranslatorInOneBatch(): void
    {
        $translator = new LetterTranslator();
        new BrailleExporter($translator, new PageFormat(braillePageNumbers: false))->layout(new Document([
            new Heading('a'),
            new Paragraph('b'),
            new PrintPage('c'),
        ], 'fr', 'T'), Contraction::Contracted);

        self::assertSame([['a', 'b', 'c']], $translator->batches);
    }

    public function testABlockOfAnotherKindIsLeftOut(): void
    {
        $rows = $this->rows([new class implements Block {}, new Paragraph('a')], new PageFormat(12, 3, braillePageNumbers: false));

        self::assertSame(['  A'], $rows);
    }

    public function testATranslatorThatGivesTooFewTextsIsRefused(): void
    {
        $translator = self::createStub(Translator::class);
        $translator->method('translate')->willReturn([]);

        $this->expectException(InvalidBraille::class);
        $this->expectExceptionMessageMatches('/gave 0 texts back for 1\.$/');

        new BrailleExporter($translator)->layout(new Document([new Paragraph('a')]));
    }

    public function testATranslatorThatGivesTextIsRefused(): void
    {
        $translator = self::createStub(Translator::class);
        $translator->method('translate')->willReturn([Ascii::braille('A'), 'b']);

        $this->expectException(InvalidBraille::class);
        $this->expectExceptionMessageMatches('/gave text 1 back with characters that are not braille cells\.$/');

        new BrailleExporter($translator)->layout(new Document([new Paragraph('a'), new Paragraph('b')]));
    }

    public function testANarrowIndentLeavesRoomForText(): void
    {
        $list = new ListBlock([new ListItem('x')]);

        for ($depth = 0; $depth < 8; ++$depth) {
            $list = new ListBlock([new ListItem('x', $list)]);
        }

        $rows = $this->rows([$list], new PageFormat(12, 20, braillePageNumbers: false));

        self::assertSame(str_repeat(' ', 10).'X', $rows[8]);
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<string>
     */
    private function rows(array $blocks, PageFormat $format): array
    {
        $book = new BrailleExporter(new LetterTranslator(), $format)->layout(new Document($blocks));

        return Ascii::rows(array_merge(...array_map(static fn (Page $page): array => $page->rows, $book->pages())));
    }
}
