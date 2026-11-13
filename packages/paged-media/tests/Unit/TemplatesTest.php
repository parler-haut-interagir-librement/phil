<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMedia\Template\AddressLabel;
use Phil\PagedMedia\Template\AddressLabels;
use Phil\PagedMedia\Template\Badge;
use Phil\PagedMedia\Template\Calendar;
use Phil\PagedMedia\Template\CalendarMonth;
use Phil\PagedMedia\Template\DownloadCard;
use Phil\PagedMedia\Template\DownloadCards;
use Phil\PagedMedia\Template\GiftCard;
use Phil\PagedMedia\Template\GiftCards;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMedia\Template\LargePrint;
use Phil\PagedMedia\Template\LargePrintBlock;
use Phil\PagedMedia\Template\Layout;
use Phil\PagedMedia\Template\NameBadges;
use Phil\PagedMedia\Template\ProductLabel;
use Phil\PagedMedia\Template\ProductLabels;
use Phil\PagedMedia\Template\ShelfLabel;
use Phil\PagedMedia\Template\ShelfLabels;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMedia\Tests\Unit\Fixture\PdfReader;
use Phil\PagedMedia\Tests\Unit\Fixture\PngBuilder;
use Phil\PagedMedia\Tests\Unit\Fixture\TrueTypeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LabelSheet::class)]
#[CoversClass(Layout::class)]
#[CoversClass(TemplateFonts::class)]
#[CoversClass(Badge::class)]
#[CoversClass(NameBadges::class)]
#[CoversClass(AddressLabel::class)]
#[CoversClass(AddressLabels::class)]
#[CoversClass(ProductLabel::class)]
#[CoversClass(ProductLabels::class)]
#[CoversClass(ShelfLabel::class)]
#[CoversClass(ShelfLabels::class)]
#[CoversClass(GiftCard::class)]
#[CoversClass(GiftCards::class)]
#[CoversClass(DownloadCard::class)]
#[CoversClass(DownloadCards::class)]
#[CoversClass(CalendarMonth::class)]
#[CoversClass(Calendar::class)]
#[CoversClass(LargePrintBlock::class)]
#[CoversClass(LargePrint::class)]
final class TemplatesTest extends TestCase
{
    public function testASheetPlacesItsLabelsRowByRowFromTheTopLeft(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 100, 80, 10, 20, 30, 5);

        self::assertSame(4, $sheet->perPage());
        self::assertSame([20.0, 110.0], $sheet->corner(0));
        self::assertSame([150.0, 110.0], $sheet->corner(1));
        self::assertSame([20.0, 25.0], $sheet->corner(2));
        self::assertSame([150.0, 25.0], $sheet->corner(3));
    }

    public function testTheNamedSheetsAreCommonStock(): void
    {
        $letter30 = LabelSheet::letter30();
        self::assertSame([3, 10, 189.0, 72.0], [$letter30->columns, $letter30->rows, $letter30->labelWidth, $letter30->labelHeight]);
        self::assertSame([13.5, 684.0], $letter30->corner(0));
        self::assertSame([211.5, 684.0], $letter30->corner(1));

        $letter10 = LabelSheet::letter10();
        self::assertSame([2, 5, 252.0, 144.0, 36.0, 54.0], [$letter10->columns, $letter10->rows, $letter10->labelWidth, $letter10->labelHeight, $letter10->top, $letter10->left]);

        $letter6 = LabelSheet::letter6();
        self::assertSame([2, 3, 288.0, 216.0, 72.0, 18.0], [$letter6->columns, $letter6->rows, $letter6->labelWidth, $letter6->labelHeight, $letter6->top, $letter6->left]);

        $a4x21 = LabelSheet::a4x21();
        self::assertSame([3, 7, 119.91, 0.0], [$a4x21->columns, $a4x21->rows, $a4x21->labelHeight, $a4x21->left]);
        self::assertEqualsWithDelta(595 / 3, $a4x21->labelWidth, 0.01);
        self::assertSame(1.31, $a4x21->top);

        $a4x8 = LabelSheet::a4x8();
        self::assertSame([2, 4], [$a4x8->columns, $a4x8->rows]);
        self::assertSame(842 / 4, $a4x8->labelHeight);
        self::assertSame(595 / 2, $a4x8->labelWidth);

        $single = LabelSheet::single(PageSize::of(243, 153));
        self::assertSame([1, 1, 243.0, 153.0, [0.0, 0.0]], [$single->columns, $single->rows, $single->labelWidth, $single->labelHeight, $single->corner(0)]);
    }

    /**
     * @param list<float|int> $arguments
     */
    #[DataProvider('invalidSheets')]
    public function testASheetIsRefusedWhenItsLabelsCannotBePlaced(array $arguments, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        LabelSheet::of(PageSize::of(300, 200), (int) $arguments[0], (int) $arguments[1], ...\array_slice($arguments, 2));
    }

    /**
     * @return iterable<string, array{list<float|int>, string}>
     */
    public static function invalidSheets(): iterable
    {
        $shape = 'A label sheet has at least one column and one row';

        yield 'no column' => [[0, 1, 10, 10], $shape];
        yield 'no row' => [[1, 0, 10, 10], $shape];
        yield 'no width' => [[1, 1, 0, 10], $shape];
        yield 'no height' => [[1, 1, 10, 0], $shape];
        yield 'a negative top' => [[1, 1, 10, 10, -1], $shape];
        yield 'a negative left' => [[1, 1, 10, 10, 0, -1], $shape];
        yield 'a negative column gap' => [[1, 1, 10, 10, 0, 0, -1], $shape];
        yield 'a negative row gap' => [[1, 1, 10, 10, 0, 0, 0, -1], $shape];
        yield 'too wide' => [[2, 1, 100, 10, 0, 50, 50.02], 'The labels of a sheet fit on its page: they need 300.02 by 10 points, the page is 300 by 200.'];
        yield 'too wide with its gaps' => [[2, 1, 140, 10, 0, 0, 40], 'they need 320 by 10 points'];
        yield 'too high with its gaps' => [[1, 2, 10, 85, 0, 0, 0, 40], 'they need 10 by 210 points'];
        yield 'too high' => [[1, 2, 10, 100, 0.02], 'they need 10 by 200.02 points'];
    }

    public function testASheetMayFillItsPageExactly(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 100, 95, 5, 50, 50, 5.01);

        self::assertSame([200.0, 0.0], array_map(round(...), $sheet->corner(3)));
    }

    public function testLabelsFlowOntoNewPages(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);
        $labels = array_map(static fn (int $i): AddressLabel => new AddressLabel(['Label '.$i]), range(1, 5));
        $document = new AddressLabels($sheet)->render($labels);

        self::assertSame(2, $document->pageCount());
        self::assertSame(0, new AddressLabels($sheet)->render([])->pageCount());
    }

    public function testAddressLabelsCentreTheirLinesTheFirstInBold(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);
        $document = new AddressLabels($sheet)->render([new AddressLabel(['Ann Lee', ' 1 Main St ', '', 'Springfield']), new AddressLabel(['B'])]);

        self::assertSame(
            "BT /F1 10 Tf 8 158 Td (Ann Lee) Tj ET\nBT /F2 10 Tf 8 146 Td (1 Main St) Tj ET\nBT /F2 10 Tf 8 134 Td (Springfield) Tj ET\nBT /F1 10 Tf 158 146 Td (B) Tj ET\n",
            $this->contents($document),
        );
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $document->render());
    }

    public function testAnAddressLabelKeepsTheLinesItsLabelHolds(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 1, 1, 300, 40);
        $contents = $this->contents(new AddressLabels($sheet, size: 10)->render([new AddressLabel(['1', '2', '3', '4'])]));

        self::assertSame(2, substr_count($contents, ' Tj '));
    }

    public function testAnAddressLabelInATinyLabelKeepsItsFirstLine(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 1, 1, 300, 10);

        self::assertSame(1, substr_count($this->contents(new AddressLabels($sheet)->render([new AddressLabel(['1', '2'])])), ' Tj '));
    }

    public function testAnAddressLabelKeepsItsLinesAsAList(): void
    {
        self::assertSame(['Ann Lee', '1 Main St', 'Springfield'], new AddressLabel(['Ann Lee', ' 1 Main St ', '', ' ', 'Springfield'])->lines);
    }

    public function testASheetMayFillItsPageToTheTolerance(): void
    {
        self::assertSame(1, LabelSheet::of(PageSize::of(300, 200), 1, 1, 300.01, 10)->columns);
        self::assertSame(1, LabelSheet::of(PageSize::of(300, 200), 1, 1, 10, 200.01)->columns);
        self::assertSame(2, LabelSheet::of(PageSize::of(300, 200), 2, 2, 100, 80, 20, 50, 50.01, 20)->columns);
    }

    public function testASheetTellsHowMuchRoomItsLabelsNeedToTheCentimetre(): void
    {
        foreach ([[1, 1, 300.0149, 10, 'they need 300.01 by 10 points'], [1, 1, 10, 200.0149, 'they need 10 by 200.01 points'], [1, 1, 300.014, 10, 'they need 300.01 by 10 points']] as [$columns, $rows, $width, $height, $message]) {
            try {
                LabelSheet::of(PageSize::of(300, 200), $columns, $rows, $width, $height);
                self::fail('The labels do not fit.');
            } catch (InvalidDocument $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testTheCutsOfATextAreMadeAtItsCharactersAndLeaveNoSpaceBeforeTheEllipsis(): void
    {
        $document = new Document(compress: false);
        $document->addPage();

        $font = StandardFont::Helvetica;

        Layout::line($document, 0, 0, $font->width("Ab\u{2026}", 10), 'AbcdefgHIJ', 10, 10, $font);
        Layout::line($document, 0, 0, $font->width("Hello \u{2026}", 10), 'Hello world', 10, 10, $font);
        Layout::line($document, 0, 0, $font->width("\u{E9}\u{E9}\u{2026}", 10), 'éééééééé', 10, 10, $font);
        Layout::line($document, 0, 0, $font->width("\u{C6}\u{2026}", 10) + 6, 'ÆÆÆÆÆ', 10, 10, $font);

        self::assertSame(
            "BT /F1 10 Tf 0 0 Td (Ab\x85) Tj ET\nBT /F1 10 Tf 0 0 Td (Hello\x85) Tj ET\nBT /F1 10 Tf 0 0 Td (\xE9\xE9\x85) Tj ET\nBT /F1 10 Tf 0 0 Td (\xC6\x85) Tj ET\n",
            new PdfReader($document->render())->contents(),
        );
    }

    public function testNameBadgesShowTheEventTheNameAndTheirLines(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);

        self::assertSame(
            "q 1 w 0.251 g 0 180 150 20 re f Q\nq 1 g BT /F1 12 Tf 12 186 Td (Con) Tj ET Q\nBT /F1 20 Tf 55.56 145 Td (Ann) Tj ET\nq 0.314 g BT /F2 12 Tf 65.33 125 Td (she) Tj ET Q\nBT /F2 11 Tf 65.83 112 Td (Org) Tj ET\n",
            $this->contents(new NameBadges('Con', $sheet)->render([new Badge('Ann', 'she', 'Org')])),
        );
    }

    public function testANameBadgeTakesItsAccentAndLogoAndShrinksALongName(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);
        $logo = Image::fromBytes($this->png(4, 2));
        $contents = $this->contents(new NameBadges('Con', $sheet, accent: Color::rgb(255, 0, 0), logo: $logo)->render([new Badge('Maximilien-Alexandre')]));

        self::assertStringStartsWith("q 1 w 1 0 0 rg 0 180 150 20 re f Q\nq 12 0 0 6 12 187 cm /Im1 Do Q\nq 1 g BT /F1 12 Tf 32 186 Td (Con) Tj ET Q\n", $contents);
        self::assertMatchesRegularExpression('/BT \/F1 (1[2-9](\.5)?) Tf [\d.]+ 145 Td \(Maximilien-Alexandre\) Tj ET\n\z/', $contents);
        self::assertSame(3, substr_count($contents, ' Tj ') + substr_count($contents, ' Do '));
    }

    public function testATextTooLongForTheSmallestSizeIsCutWithAnEllipsis(): void
    {
        $document = new Document(compress: false);
        $document->addPage();

        self::assertSame(6.0, Layout::line($document, 0, 0, 30, 'A long organisation name', 10, 6, StandardFont::Helvetica));
        self::assertSame(10.0, Layout::line($document, 0, 0, 30, '', 10, 6, StandardFont::Helvetica));
        self::assertSame(7.0, Layout::line($document, 0, 0, 30, 'Abcdefgh', 10, 6, StandardFont::Helvetica, true));
        self::assertSame(10.0, Layout::line($document, 0, 0, 1, 'Ab', 10, 10, StandardFont::Helvetica));

        $contents = new PdfReader($document->render())->contents();
        self::assertSame("BT /F1 6 Tf 0 0 Td (A long o\x85) Tj ET\nBT /F1 7 Tf 0.21 0 Td (Abcdefgh) Tj ET\nBT /F1 10 Tf 0 0 Td (\x85) Tj ET\n", $contents);
    }

    public function testAParagraphCutsItsLastLineWhenTextRemains(): void
    {
        $document = new Document(compress: false);
        $document->addPage();

        self::assertSame(80.0, Layout::paragraph($document, 0, 100, 40, 'one two three four five', 10, 10, 2, StandardFont::Helvetica));
        self::assertSame(90.0, Layout::paragraph($document, 0, 100, 400, 'short', 10, 10, 2, StandardFont::Helvetica));
        self::assertSame(90.0, Layout::paragraph($document, 0, 100, 400, '', 10, 10, 2, StandardFont::Helvetica));

        self::assertSame("BT /F1 10 Tf 0 100 Td (one two) Tj ET\nBT /F1 10 Tf 0 90 Td (three f\x85) Tj ET\nBT /F1 10 Tf 0 100 Td (short) Tj ET\n", new PdfReader($document->render())->contents());
    }

    public function testAFittedSizeStepsByHalfPoints(): void
    {
        self::assertSame(16.0, Layout::fittedSize('Abcdef', 50, 20, 6, StandardFont::Helvetica));
        self::assertSame(20.0, Layout::fittedSize('A', 50, 20, 6, StandardFont::Helvetica));
        self::assertSame(6.0, Layout::fittedSize('Abcdefghijklmnop', 10, 20, 6, StandardFont::Helvetica));
    }

    public function testAnImageIsCentredInItsBoxWithItsProportions(): void
    {
        $document = new Document(compress: false);
        $document->addPage();
        Layout::image($document, Image::fromBytes($this->png(4, 2)), 10, 20, 40, 40);
        Layout::image($document, Image::fromBytes($this->png(2, 4)), 10, 20, 40, 40);

        self::assertSame("q 40 0 0 20 10 30 cm /Im1 Do Q\nq 20 0 0 40 20 20 cm /Im2 Do Q\n", new PdfReader($document->render())->contents());
    }

    public function testProductLabelsShowTheNameDetailPriceAndSku(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);

        self::assertSame(
            "BT /F1 10 Tf 6 184 Td (Shirt) Tj ET\nq 0.314 g BT /F2 8 Tf 6 172.5 Td (Blue) Tj ET Q\nBT /F1 14 Tf 6 106 Td ($9) Tj ET\nBT /F2 7 Tf 125.72 107.75 Td (SKU1) Tj ET\n",
            $this->contents(new ProductLabels($sheet)->render([new ProductLabel('Shirt', '$9', 'SKU1', 'Blue')])),
        );
    }

    public function testAProductLabelMakesRoomForItsCode(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);
        $contents = $this->contents(new ProductLabels($sheet)->render([new ProductLabel('Shirt', '$9', code: Image::fromBytes($this->png(2, 2)))]));

        self::assertSame("q 88 0 0 88 56 106 cm /Im1 Do Q\nBT /F1 10 Tf 6 184 Td (Shirt) Tj ET\nBT /F1 14 Tf 6 106 Td ($9) Tj ET\n", $contents);
    }

    public function testShelfLabelsShowThePriceLargeAndTheFoot(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);

        self::assertSame(
            "BT /F1 12 Tf 6 182 Td (Dice) Tj ET\nq 0.5 w 0.627 G 6 176 m 144 176 l S Q\nBT /F1 35 Tf 105.08 142 Td ($5) Tj ET\nBT /F2 8 Tf 117.76 130 Td ($1 / die) Tj ET\nq 0.314 g BT /F2 7 Tf 6 106 Td (D7 \x97 A3) Tj ET Q\n",
            $this->contents(new ShelfLabels($sheet)->render([new ShelfLabel('Dice', '$5', '$1 / die', 'D7', 'A3')])),
        );
    }

    public function testAShelfLabelWithItsCodeAndNoFoot(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);
        $contents = $this->contents(new ShelfLabels($sheet)->render([new ShelfLabel('Dice', '$5', location: '', code: Image::fromBytes($this->png(2, 1)))]));

        self::assertStringContainsString("q 41.4 0 0 20.7 6 128.15 cm /Im1 Do Q\n", $contents);
        self::assertSame(2, substr_count($contents, ' Tj '));
    }

    public function testGiftCardsKeepTheirFootClearOfTheMessage(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);

        self::assertSame(
            "BT /F1 12 Tf 10 176 Td (Gift) Tj ET\nBT /F1 22 Tf 10 151 Td ($50) Tj ET\nq 0.314 g BT /F2 7 Tf 10 110 Td (Valid until Dec) Tj ET Q\nBT /F2 8 Tf 10 120 Td (PIN 12) Tj ET\nBT /F1 9 Tf 10 131 Td (Code GC1) Tj ET\n",
            $this->contents(new GiftCards('Gift', $sheet, cutLines: false)->render([new GiftCard('$50', 'GC1', '12', 'For Ann', 'Hi', 'Dec')])),
        );
    }

    public function testAGiftCardOnLargerStockShowsItsRecipientMessageLogoAndCode(): void
    {
        $sheet = LabelSheet::single(PageSize::of(252, 144));
        $png = Image::fromBytes($this->png(2, 2));
        $contents = $this->contents(new GiftCards('Carte-cadeau', $sheet, logo: $png, codeLabel: 'Code', pinLabel: 'NIP', validUntilLabel: 'Valide')->render([new GiftCard('50 $', 'GC1', recipient: 'Pour Ann', message: 'Joyeux anniversaire, que tes dés roulent toujours des vingt.', codeImage: $png)]));

        self::assertStringStartsWith("q 0.25 w 0.784 G 0 0 252 144 re S Q\nq 20 0 0 20 10 114 cm /Im1 Do Q\nBT /F1 12 Tf 36 120 Td (Carte-cadeau) Tj ET\nq 57.6 0 0 57.6 184.4 10 cm /Im1 Do Q\n", $contents);
        self::assertStringContainsString('(Pour Ann) Tj', $contents);
        self::assertStringContainsString('(Joyeux anniversaire, que tes d', $contents);
        self::assertStringEndsWith("BT /F1 9 Tf 10 10 Td (Code GC1) Tj ET\n", $contents);
        self::assertStringNotContainsString('NIP', $contents);
    }

    public function testDownloadCardsShowTheirCodeInABox(): void
    {
        $sheet = LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100);

        self::assertSame(
            "BT /F1 12 Tf 10 176 Td (Book) Tj ET\nq 0.314 g BT /F2 8 Tf 10 164 Td (PDF) Tj ET Q\nBT /F2 8 Tf 10 152 Td (Enter at) Tj ET\nBT /F1 9 Tf 10 142.5 Td (ex.com) Tj ET\nq 0.5 w 0.941 g 0.471 G 10 110 130 22 re B Q\nBT /F1 11 Tf 67.97 116.6 Td (K7) Tj ET\n",
            $this->contents(new DownloadCards('Enter at', 'ex.com', $sheet, cutLines: false)->render([new DownloadCard('Book', 'K7', 'PDF')])),
        );
    }

    public function testADownloadCardMakesRoomForItsImagesAndCutLines(): void
    {
        $sheet = LabelSheet::single(PageSize::of(252, 144));
        $png = Image::fromBytes($this->png(2, 2));
        $contents = $this->contents(new DownloadCards('Enter at', 'ex.com', $sheet, logo: $png)->render([new DownloadCard('Book', 'K7', codeImage: $png)]));

        self::assertStringStartsWith("q 0.25 w 0.784 G 0 0 252 144 re S Q\nq 81.2 0 0 81.2 160.8 31.4 cm /Im1 Do Q\nq 20 0 0 20 10 114 cm /Im1 Do Q\nBT /F1 12 Tf 36 120 Td (Book) Tj ET\n", $contents);
        self::assertStringContainsString('10 10 140.8 26 re B Q', $contents);
    }

    public function testACalendarStartsItsWeeksOnTheDayAsked(): void
    {
        $month = new CalendarMonth(2026, 2, 'Feb', [3 => ['Game', 'Night', 'Late', 'Later']]);
        $monday = $this->contents(new Calendar(['M', 'T', 'W', 'T', 'F', 'S', 'S'], page: PageSize::of(400, 300))->render([$month]));
        $sunday = $this->contents(new Calendar(['S', 'M', 'T', 'W', 'T', 'F', 'S'], 7, PageSize::of(400, 300), accent: Color::rgb(0, 0, 255))->render([$month]));

        self::assertStringStartsWith("BT /F1 24 Tf 178.66 240 Td (Feb) Tj ET\nq 1 w 0.251 g 36 216 46.86 18 re f Q\nq 1 g BT /F1 10 Tf 55.26 221 Td (M) Tj ET Q\n", $monday);
        self::assertSame(35, substr_count($monday, ' re S Q'));
        self::assertStringContainsString("q 0.5 w 0.627 G 317.14 178.4 46.86 35.6 re S Q\nBT /F1 10 Tf 321.14 202 Td (1) Tj ET\n", $monday);
        self::assertStringContainsString("BT /F1 10 Tf 86.86 166.4 Td (3) Tj ET\nBT /F2 7 Tf 86.86 154.4 Td (Game) Tj ET\nq", $monday);
        self::assertStringNotContainsString('(Night)', $monday);
        self::assertStringEndsWith("BT /F1 10 Tf 274.29 59.6 Td (28) Tj ET\nq 0.5 w 0.627 G 317.14 36 46.86 35.6 re S Q\n", $monday);
        self::assertSame(28, substr_count($sunday, ' re S Q'));
        self::assertStringContainsString('q 1 w 0 0 1 rg 36 216', $sunday);
        self::assertStringContainsString('BT /F1 10 Tf 40 ', $sunday);
    }

    public function testACalendarPrintsAMonthPerPageOnLandscapeLetter(): void
    {
        $document = new Calendar(['1', '2', '3', '4', '5', '6', '7'])->render([new CalendarMonth(2026, 11, 'N'), new CalendarMonth(2026, 12, 'D')]);

        self::assertSame(2, $document->pageCount());
        self::assertStringContainsString('/MediaBox [0 0 792 612]', $document->render());
    }

    public function testACalendarMonthKnowsItsDays(): void
    {
        self::assertSame(29, new CalendarMonth(2028, 2, 'F')->days());
        self::assertSame(31, new CalendarMonth(9999, 12, 'D', [31 => ['x']])->days());
        self::assertSame('0001-01-01', new CalendarMonth(1, 1, 'J', [1 => []])->first()->format('Y-m-d'));
    }

    public function testLargePrintSetsHeadingsAndParagraphsWithPageNumbers(): void
    {
        $document = new LargePrint(PageSize::of(300, 300), margin: 36)->render([LargePrintBlock::heading('Title'), LargePrintBlock::paragraph('One two three four five six seven eight nine ten')]);

        self::assertSame(
            "BT /F1 18 Tf 145 18 Td (1) Tj ET\nBT /F2 24 Tf 36 240 Td (Title) Tj ET\nBT /F1 18 Tf 36 196.5 Td (One two three four five six) Tj ET\nBT /F1 18 Tf 36 169.5 Td (seven eight nine ten) Tj ET\n",
            $this->contents($document),
        );
    }

    public function testLargePrintFlowsOntoNewPagesAndKeepsAHeadingWithItsText(): void
    {
        $blocks = [LargePrintBlock::paragraph(str_repeat('word ', 60)), LargePrintBlock::heading('Next'), LargePrintBlock::paragraph('End')];
        $document = new LargePrint(PageSize::of(300, 300), size: 20, headingSize: 20, margin: 36, pageNumbers: false)->render($blocks);
        $contents = $this->contents($document);

        self::assertGreaterThan(1, $document->pageCount());
        self::assertStringNotContainsString(' 18 Td ', $contents);
        self::assertMatchesRegularExpression('/\(word( word)*\) Tj ET\nBT \/F2 20 Tf 36 244 Td \(Next\) Tj ET\n/', $contents);
    }

    public function testLargePrintAtALargeSizeFlowsOverPages(): void
    {
        $document = new LargePrint(PageSize::of(300, 200), size: 40, headingSize: 40, margin: 18, pageNumbers: false)->render([LargePrintBlock::paragraph(str_repeat('word ', 12))]);

        self::assertSame(2, $document->pageCount());
        self::assertSame(6, substr_count($this->contents($document), ' Tj '));
    }

    public function testATemplateWritesInItsFontsWithItsMetadataAndConformance(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x2026 => 1000])->build());
        $fonts = new TemplateFonts($font, $font);
        $pdf = new AddressLabels(LabelSheet::letter30(), $fonts, metadata: new Metadata('Labels'), conformance: Conformance::PdfA2u)->render([new AddressLabel(['A'])])->render();

        self::assertStringContainsString('/Title (Labels)', $pdf);
        self::assertStringContainsString('/GTS_PDFA1', $pdf);
        self::assertSame(StandardFont::Helvetica, new TemplateFonts()->regular);
        self::assertSame(StandardFont::HelveticaBold, new TemplateFonts()->bold);
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('invalidItems')]
    public function testAnInvalidItemIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidItems(): iterable
    {
        yield 'a badge without a name' => [static fn (): Badge => new Badge(' '), 'A badge has a name.'];
        yield 'a blank badge name' => [static fn (): Badge => new Badge("\t"), 'A badge has a name.'];
        yield 'an empty address' => [static fn (): AddressLabel => new AddressLabel(['', ' ']), 'An address label has at least one line.'];
        yield 'a product without a name' => [static fn (): ProductLabel => new ProductLabel(' ', '$1'), 'A product label has a name and a price.'];
        yield 'a product without a price' => [static fn (): ProductLabel => new ProductLabel('A', ' '), 'A product label has a name and a price.'];
        yield 'a shelf label without a name' => [static fn (): ShelfLabel => new ShelfLabel('', '$1'), 'A shelf label has a name and a price.'];
        yield 'a shelf label with a blank name' => [static fn (): ShelfLabel => new ShelfLabel(' ', '$1'), 'A shelf label has a name and a price.'];
        yield 'a shelf label with a blank price' => [static fn (): ShelfLabel => new ShelfLabel('A', ' '), 'A shelf label has a name and a price.'];
        yield 'a gift card with a blank amount' => [static fn (): GiftCard => new GiftCard(' ', 'C'), 'A gift card has an amount and a code.'];
        yield 'a download card with a blank title' => [static fn (): DownloadCard => new DownloadCard(' ', 'C'), 'A download card has a title and a code.'];
        yield 'a download card with a blank code' => [static fn (): DownloadCard => new DownloadCard('T', ' '), 'A download card has a title and a code.'];
        yield 'a shelf label without a price' => [static fn (): ShelfLabel => new ShelfLabel('A', ''), 'A shelf label has a name and a price.'];
        yield 'a gift card without an amount' => [static fn (): GiftCard => new GiftCard('', 'C'), 'A gift card has an amount and a code.'];
        yield 'a gift card without a code' => [static fn (): GiftCard => new GiftCard('$1', ' '), 'A gift card has an amount and a code.'];
        yield 'a download card without a title' => [static fn (): DownloadCard => new DownloadCard('', 'C'), 'A download card has a title and a code.'];
        yield 'a download card without a code' => [static fn (): DownloadCard => new DownloadCard('T', ''), 'A download card has a title and a code.'];
        yield 'month 0' => [static fn (): CalendarMonth => new CalendarMonth(2026, 0, 'X'), 'A calendar month is a month from 1 to 12 of a year from 1 to 9999; 2026-0 is not.'];
        yield 'month 13' => [static fn (): CalendarMonth => new CalendarMonth(2026, 13, 'X'), '2026-13 is not.'];
        yield 'year 0' => [static fn (): CalendarMonth => new CalendarMonth(0, 1, 'X'), '0-1 is not.'];
        yield 'year 10000' => [static fn (): CalendarMonth => new CalendarMonth(10_000, 1, 'X'), '10000-1 is not.'];
        yield 'an event on day 0' => [static fn (): CalendarMonth => new CalendarMonth(2026, 2, 'Feb', [0 => ['x']]), 'The events of Feb fall on days from 1 to 28; 0 is not.'];
        yield 'an event on day 29' => [static fn (): CalendarMonth => new CalendarMonth(2026, 2, 'Feb', [29 => ['x']]), '29 is not.'];
        yield 'six weekdays' => [static fn (): Calendar => new Calendar(['1', '2', '3', '4', '5', '6']), 'A calendar names the seven days of the week.'];
        yield 'first day 0' => [static fn (): Calendar => new Calendar(['1', '2', '3', '4', '5', '6', '7'], 0), 'The first day of a week is from 1, Monday, to 7, Sunday; 0 is not.'];
        yield 'first day 8' => [static fn (): Calendar => new Calendar(['1', '2', '3', '4', '5', '6', '7'], 8), '8 is not.'];
        yield 'small print' => [static fn (): LargePrint => new LargePrint(size: 17.5), 'Large print is set in 18 points or more, its headings no smaller; 17.5 and 24 are not.'];
        yield 'small headings' => [static fn (): LargePrint => new LargePrint(size: 20, headingSize: 19.5), '20 and 19.5 are not.'];
        yield 'narrow margins' => [static fn (): LargePrint => new LargePrint(margin: 17.5), 'The margins of a large-print page are 18 points or more and leave room for text; 17.5 does not.'];
        yield 'margins without room' => [static fn (): LargePrint => new LargePrint(PageSize::of(300, 300), margin: 102.5), '102.5 does not.'];
    }

    public function testTheLargestMarginsAndSmallestSizesAreTaken(): void
    {
        self::assertSame(1, new LargePrint(PageSize::of(300, 300), margin: 102)->render([])->pageCount());
        self::assertSame(1, new LargePrint(size: 18, headingSize: 18, margin: 18)->render([])->pageCount());
        self::assertSame(1, new LargePrint(PageSize::of(300, 300), margin: 102, pageNumbers: false)->render([LargePrintBlock::heading('')])->pageCount());
    }

    private function png(int $width, int $height): string
    {
        return PngBuilder::build($width, $height, 0, 8, array_fill(0, $height, str_repeat("\x80", $width)));
    }

    private function contents(Document $document): string
    {
        return new PdfReader($document->render())->contents();
    }
}
