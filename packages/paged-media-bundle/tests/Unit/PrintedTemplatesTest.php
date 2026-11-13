<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Exception\InvalidFont;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMediaBundle\Application\Exception\InvalidTemplateParameters;
use Phil\PagedMediaBundle\Infrastructure\Template\AddressLabelsTemplate;
use Phil\PagedMediaBundle\Infrastructure\Template\CalendarTemplate;
use Phil\PagedMediaBundle\Infrastructure\Template\DownloadCardsTemplate;
use Phil\PagedMediaBundle\Infrastructure\Template\GiftCardsTemplate;
use Phil\PagedMediaBundle\Infrastructure\Template\LargePrintTemplate;
use Phil\PagedMediaBundle\Infrastructure\Template\NameBadgesTemplate;
use Phil\PagedMediaBundle\Infrastructure\Template\Parameters;
use Phil\PagedMediaBundle\Infrastructure\Template\PrintedTemplates;
use Phil\PagedMediaBundle\Infrastructure\Template\ProductLabelsTemplate;
use Phil\PagedMediaBundle\Infrastructure\Template\ShelfLabelsTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parameters::class)]
#[CoversClass(PrintedTemplates::class)]
#[CoversClass(InvalidTemplateParameters::class)]
#[CoversClass(NameBadgesTemplate::class)]
#[CoversClass(AddressLabelsTemplate::class)]
#[CoversClass(ProductLabelsTemplate::class)]
#[CoversClass(ShelfLabelsTemplate::class)]
#[CoversClass(GiftCardsTemplate::class)]
#[CoversClass(DownloadCardsTemplate::class)]
#[CoversClass(CalendarTemplate::class)]
#[CoversClass(LargePrintTemplate::class)]
final class PrintedTemplatesTest extends TestCase
{
    public function testEveryTemplateIsKnownByItsName(): void
    {
        self::assertSame([
            'paged_media.name_badges', 'paged_media.address_labels', 'paged_media.product_labels', 'paged_media.shelf_labels',
            'paged_media.gift_cards', 'paged_media.download_cards', 'paged_media.calendar', 'paged_media.large_print',
        ], array_keys(PrintedTemplates::TEMPLATES));
    }

    public function testNameBadgesAreDrawnFromParameters(): void
    {
        $pdf = new NameBadgesTemplate()->build(['event' => 'Con', 'accent' => '#336699', 'sheet' => 'letter10', 'title' => 'Badges', 'badges' => [['name' => 'Ann', 'subtitle' => 'she', 'organisation' => 'Org'], ['name' => 'Bob']]]);
        $text = $this->text($pdf);

        self::assertSame(1, $pdf->pageCount());
        self::assertStringContainsString('(Ann) Tj', $text);
        self::assertStringContainsString('(she) Tj', $text);
        self::assertStringContainsString('(Org) Tj', $text);
        self::assertStringContainsString('(Bob) Tj', $text);
        self::assertStringContainsString('0.2 0.4 0.6 rg 54 727.2 252 28.8 re f', $text);
        self::assertStringContainsString('/Title (Badges)', $pdf->render());
    }

    public function testAddressLabelsAreDrawnFromParameters(): void
    {
        $pdf = new AddressLabelsTemplate()->build(['size' => 12, 'labels' => [['lines' => ['Ann Lee', 'Springfield']]]]);

        self::assertStringContainsString('BT /F1 12 Tf 21.5 722.4 Td (Ann Lee) Tj ET', $this->text($pdf));
    }

    public function testProductAndShelfLabelsAreDrawnFromParameters(): void
    {
        $product = $this->text(new ProductLabelsTemplate()->build(['labels' => [['name' => 'Shirt', 'price' => '$9', 'sku' => 'S1', 'detail' => 'Blue']]]));
        $shelf = $this->text(new ShelfLabelsTemplate()->build(['labels' => [['name' => 'Dice', 'price' => '$5', 'unit_price' => '$1 / die', 'sku' => 'D7', 'location' => 'A3']]]));

        foreach (['(Shirt)', '($9)', '(S1)', '(Blue)'] as $text) {
            self::assertStringContainsString($text, $product);
        }

        foreach (['(Dice)', '($5)', '($1 / die)', "(D7 \x97 A3)"] as $text) {
            self::assertStringContainsString($text, $shelf);
        }
    }

    public function testGiftCardsAreDrawnFromParametersWithTheirWords(): void
    {
        $text = $this->text(new GiftCardsTemplate()->build([
            'card_title' => 'Carte-cadeau', 'code_label' => 'Numéro', 'pin_label' => 'NIP', 'valid_until_label' => 'Valide', 'cut_lines' => false,
            'cards' => [['amount' => '50 $', 'code' => 'GC1', 'pin' => '12', 'recipient' => 'Ann', 'message' => 'Hi', 'valid_until' => '2027']],
        ]));

        foreach (['(Carte-cadeau)', '(50 $)', "(Num\xE9ro GC1)", '(NIP 12)', '(Valide 2027)', '(Ann)', '(Hi)'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }

        self::assertStringNotContainsString(' re S Q', $text);
    }

    public function testGiftCardsHaveEnglishWordsAndCutLinesByDefault(): void
    {
        $text = $this->text(new GiftCardsTemplate()->build(['card_title' => 'Gift', 'cards' => [['amount' => '$5', 'code' => 'C', 'pin' => 'P', 'valid_until' => 'D']]]));

        foreach (['(Code C)', '(PIN P)', '(Valid until D)', ' re S Q'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
    }

    public function testDownloadCardsAreDrawnFromParameters(): void
    {
        $text = $this->text(new DownloadCardsTemplate()->build(['instructions' => 'Enter at', 'address' => 'ex.com', 'cut_lines' => false, 'cards' => [['title' => 'Book', 'code' => 'K7', 'subtitle' => 'PDF'], ['title' => 'Book', 'code' => 'K8']]]));

        foreach (['(Book)', '(PDF)', '(Enter at)', '(ex.com)', '(K7)', '(K8)'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }

        self::assertStringNotContainsString('0.784 G', $text);
        self::assertStringContainsString('0.784 G', $this->text(new DownloadCardsTemplate()->build(['instructions' => 'I', 'address' => 'a', 'cards' => [['title' => 'T', 'code' => 'C']]])));
    }

    public function testACalendarIsDrawnFromParameters(): void
    {
        $pdf = new CalendarTemplate()->build([
            'weekdays' => ['S', 'M', 'T', 'W', 'T', 'F', 'S'], 'first_day' => 7, 'accent' => '#ff0000',
            'months' => [['year' => 2026, 'month' => 11, 'title' => 'November', 'events' => ['11' => ['Game night'], 24 => ['Release']]], ['year' => 2026, 'month' => 12, 'title' => 'December']],
        ]);
        $text = $this->text($pdf);

        self::assertSame(2, $pdf->pageCount());

        foreach (['(November)', '(December)', '(Game night)', '(Release)', '1 0 0 rg'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }

        self::assertStringContainsString('/MediaBox [0 0 792 612]', $pdf->render());
    }

    public function testACalendarStartsOnMondayByDefault(): void
    {
        $text = $this->text(new CalendarTemplate()->build(['weekdays' => ['M', 'T', 'W', 'T', 'F', 'S', 'S'], 'months' => [['year' => 2026, 'month' => 11, 'title' => 'N']]]));

        self::assertSame(42, substr_count($text, ' re S Q'));
    }

    public function testALargePrintEditionIsDrawnFromParameters(): void
    {
        $text = $this->text(new LargePrintTemplate()->build(['size' => 20, 'heading_size' => 30, 'page_numbers' => false, 'blocks' => [['heading' => 'Title'], ['paragraph' => 'Text']]]));

        self::assertStringContainsString("BT /F1 30 Tf 72 690 Td (Title) Tj ET\nBT /F2 20 Tf 72 640 Td (Text) Tj ET\n", $text);
        self::assertStringNotContainsString('(1) Tj', $text);
        self::assertStringContainsString('(1) Tj', $this->text(new LargePrintTemplate()->build(['blocks' => [['paragraph' => 'Text']]])));
        self::assertStringContainsString('BT /F1 18 Tf 72 702 Td (Text) Tj ET', $this->text(new LargePrintTemplate()->build(['blocks' => [['paragraph' => 'Text']]])));
    }

    public function testTheTemplatesWriteInHelveticaByDefault(): void
    {
        $fonts = PrintedTemplates::fonts(null, null);

        self::assertSame([StandardFont::Helvetica, StandardFont::HelveticaBold], [$fonts->regular, $fonts->bold]);
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', new AddressLabelsTemplate($fonts)->build(['labels' => [['lines' => ['A']]]])->render());
    }

    /**
     * @param array{?string, ?string} $files
     */
    #[DataProvider('fontFiles')]
    public function testTheConfiguredFontsAreReadFromTheirFiles(array $files, string $read): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The font file "%s" cannot be read.', $read));

        PrintedTemplates::fonts(...$files);
    }

    /**
     * @return iterable<string, array{array{?string, ?string}, string}>
     */
    public static function fontFiles(): iterable
    {
        yield 'the regular font' => [['/missing/regular.ttf', null], '/missing/regular.ttf'];
        yield 'the bold font alone' => [[null, '/missing/bold.ttf'], '/missing/bold.ttf'];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('invalidParameters')]
    public function testParametersOfTheWrongKindAreRefusedWithTheirPath(string $class, array $parameters, string $message): void
    {
        $template = new $class();
        self::assertTrue(method_exists($template, 'build'));

        $this->expectException(InvalidTemplateParameters::class);
        $this->expectExceptionMessageIsOrContains($message);

        $template->build($parameters);
    }

    /**
     * @return iterable<string, array{class-string, array<string, mixed>, string}>
     */
    public static function invalidParameters(): iterable
    {
        yield 'no event' => [NameBadgesTemplate::class, ['badges' => []], 'The template "paged_media.name_badges" expects a string at "event".'];
        yield 'no badges' => [NameBadgesTemplate::class, ['event' => 'E'], 'expects a list at "badges".'];
        yield 'badges as an object' => [NameBadgesTemplate::class, ['event' => 'E', 'badges' => ['a' => []]], 'expects a list at "badges".'];
        yield 'a badge as a string' => [NameBadgesTemplate::class, ['event' => 'E', 'badges' => ['Ann']], 'expects an object at "badges.0".'];
        yield 'a badge without a name' => [NameBadgesTemplate::class, ['event' => 'E', 'badges' => [['name' => 'A'], ['subtitle' => 'x']]], 'expects a string at "badges.1.name".'];
        yield 'a subtitle as a number' => [NameBadgesTemplate::class, ['event' => 'E', 'badges' => [['name' => 'A', 'subtitle' => 3]]], 'expects a string at "badges.0.subtitle".'];
        yield 'an unknown sheet' => [NameBadgesTemplate::class, ['event' => 'E', 'badges' => [], 'sheet' => 'a3'], 'expects one of letter30, letter10, letter6, a4x21, a4x8 at "sheet".'];
        yield 'a title as a number' => [NameBadgesTemplate::class, ['event' => 'E', 'badges' => [], 'title' => 1], 'expects a string at "title".'];
        yield 'lines as a string' => [AddressLabelsTemplate::class, ['labels' => [['lines' => 'Ann']]], 'expects a list of strings at "labels.0.lines".'];
        yield 'lines as an object' => [AddressLabelsTemplate::class, ['labels' => [['lines' => ['a' => 'Ann']]]], 'expects a list of strings at "labels.0.lines".'];
        yield 'a line as a number' => [AddressLabelsTemplate::class, ['labels' => [['lines' => ['Ann', 3]]]], 'expects a list of strings at "labels.0.lines".'];
        yield 'a size as a string' => [AddressLabelsTemplate::class, ['labels' => [], 'size' => '10'], 'expects a number at "size".'];
        yield 'cut lines as a string' => [GiftCardsTemplate::class, ['card_title' => 'G', 'cards' => [], 'cut_lines' => 'no'], 'expects true or false at "cut_lines".'];
        yield 'a year as a string' => [CalendarTemplate::class, ['weekdays' => ['1', '2', '3', '4', '5', '6', '7'], 'months' => [['year' => '2026', 'month' => 1, 'title' => 'J']]], 'expects an integer at "months.0.year".'];
        yield 'events as a list of strings' => [CalendarTemplate::class, ['weekdays' => ['1', '2', '3', '4', '5', '6', '7'], 'months' => [['year' => 2026, 'month' => 1, 'title' => 'J', 'events' => 'x']]], 'expects an object at "months.0.events".'];
        yield 'an event day that is not a day' => [CalendarTemplate::class, ['weekdays' => ['1', '2', '3', '4', '5', '6', '7'], 'months' => [['year' => 2026, 'month' => 1, 'title' => 'J', 'events' => ['first' => ['x']]]]], 'expects a day of the month as a key at "months.0.events.first".'];
        yield 'an event day of zero' => [CalendarTemplate::class, ['weekdays' => ['1', '2', '3', '4', '5', '6', '7'], 'months' => [['year' => 2026, 'month' => 1, 'title' => 'J', 'events' => ['01' => ['x']]]]], 'at "months.0.events.01".'];
        yield 'an accent as a number' => [CalendarTemplate::class, ['weekdays' => ['1', '2', '3', '4', '5', '6', '7'], 'months' => [], 'accent' => 1], 'expects a string at "accent".'];
        yield 'a block without text' => [LargePrintTemplate::class, ['blocks' => [['quote' => 'x']]], 'expects a string at "blocks.0.paragraph".'];
    }

    public function testTheDefaultsOfTheTemplatesAreThoseOfTheLibrary(): void
    {
        self::assertStringContainsString('BT /F1 10 Tf 21.5 716 Td (Ann Lee) Tj ET', $this->text(new AddressLabelsTemplate()->build(['labels' => [['lines' => ['Ann Lee']]]])));
        self::assertStringContainsString('BT /F2 24 Tf 72 ', $this->text(new LargePrintTemplate()->build(['blocks' => [['heading' => 'Title']]])));
    }

    public function testACalendarMonthWithoutItsYearOrItsMonthIsRefusedByTheLibrary(): void
    {
        foreach ([[[], '0-0 is not.'], [['year' => 2026], '2026-0 is not.'], [['month' => 5], '0-5 is not.']] as [$month, $message]) {
            try {
                new CalendarTemplate()->build(['weekdays' => ['1', '2', '3', '4', '5', '6', '7'], 'months' => [$month + ['title' => 'X']]]);
                self::fail('A month without its year or its month is refused.');
            } catch (InvalidDocument $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testTheDaysOfAMonthAreTheKeysOfItsEventsAsIntegers(): void
    {
        self::assertSame([11, 24, 5], new Parameters('t', ['11' => [], 24 => [], 5 => []])->integerKeys());

        $this->expectException(InvalidTemplateParameters::class);

        new Parameters('t', ['first' => []])->integerKeys();
    }

    public function testEveryNamedSheetIsTheLibrarysSheet(): void
    {
        $default = LabelSheet::of(PageSize::of(300, 200), 1, 1, 100, 100);

        foreach (['letter30' => LabelSheet::letter30(), 'letter10' => LabelSheet::letter10(), 'letter6' => LabelSheet::letter6(), 'a4x21' => LabelSheet::a4x21(), 'a4x8' => LabelSheet::a4x8()] as $name => $sheet) {
            self::assertEquals($sheet, new Parameters('t', ['sheet' => $name])->sheet($default));
        }

        self::assertSame($default, new Parameters('t', [])->sheet($default));
    }

    public function testANumberIsReadAsAFloat(): void
    {
        self::assertSame(12.0, new Parameters('t', ['size' => 12])->float('size', 10));
        self::assertSame(12.5, new Parameters('t', ['size' => 12.5])->float('size', 10));
        self::assertSame(10.0, new Parameters('t', [])->float('size', 10));
    }

    public function testTheLibraryRefusesWhatItsItemsRefuse(): void
    {
        $this->expectException(InvalidDocument::class);

        new GiftCardsTemplate()->build(['card_title' => 'G', 'cards' => [['amount' => '', 'code' => 'C']]]);
    }

    public function testAColourIsReadAsTheLibraryReadsIt(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('A colour is written #rrggbb or #rgb; "red" is not.');

        new NameBadgesTemplate()->build(['event' => 'E', 'badges' => [], 'accent' => 'red']);
    }

    private function text(Document $document): string
    {
        $pdf = $document->render();
        $text = '';
        preg_match_all('/<< \/Filter \/FlateDecode \/Length (\d+) >>\nstream\n/', $pdf, $streams, \PREG_OFFSET_CAPTURE);

        foreach ($streams[0] as $index => [$header, $offset]) {
            $data = gzuncompress(substr($pdf, $offset + \strlen($header), (int) $streams[1][$index][0]));
            $text .= false === $data ? '' : $data;
        }

        return $text;
    }
}
