<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Fixture;

use InvalidArgumentException;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Form\TextField;
use Phil\PagedMedia\Pdf\Number;
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
use Phil\PagedMedia\Template\NameBadges;
use Phil\PagedMedia\Template\ProductLabel;
use Phil\PagedMedia\Template\ProductLabels;
use Phil\PagedMedia\Template\ShelfLabel;
use Phil\PagedMedia\Template\ShelfLabels;

/**
 * The templates drawn on sheets of every size, with short, long and missing texts, and on pages and cards of every
 * height a half point apart: each scenario is the content stream a template writes, kept as its SHA-256 in
 * `golden/templates.json`, so that every position, size and bound of a template is held by its test. To see a
 * scenario, read it from `of()`; after a change of a template meant to move things, run `write()` from a script.
 */
final class TemplateScenarios
{
    private const string FILE = __DIR__.'/golden/templates.json';

    private const string LONG = 'A very long text that shrinks and is cut with an ellipsis, since no label holds it whole';

    /**
     * @return list<string>
     */
    public static function families(): array
    {
        return ['address', 'product', 'badge', 'shelf', 'gift', 'gift-height', 'download', 'download-height', 'calendar', 'calendar-height', 'large', 'large-height', 'form-width', 'form-height'];
    }

    /**
     * @return array<string, string> each scenario's SHA-256
     */
    public static function expected(string $family): array
    {
        /** @var array<string, array<string, string>> $expected */
        $expected = json_decode((string) file_get_contents(self::FILE), true, flags: \JSON_THROW_ON_ERROR);

        return $expected[$family] ?? [];
    }

    public static function write(): void
    {
        $all = [];

        foreach (self::families() as $family) {
            $all[$family] = array_map(static fn (string $content): string => hash('sha256', $content), self::of($family));
        }

        file_put_contents(self::FILE, json_encode($all, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * @return array<string, string> each scenario's content stream
     */
    public static function of(string $family): array
    {
        $scenarios = [];

        foreach (self::documents($family) as $name => $document) {
            $scenarios[$name] = \is_string($document) ? $document : new PdfReader($document->render())->contents();
        }

        return $scenarios;
    }

    /**
     * @return iterable<string, Document|string>
     */
    private static function documents(string $family): iterable
    {
        $png = Image::fromBytes(PngBuilder::build(3, 2, 0, 8, array_fill(0, 2, str_repeat("\x80", 3))));

        return match ($family) {
            'address' => self::address(),
            'product' => self::product($png),
            'badge' => self::badge($png),
            'shelf' => self::shelf($png),
            'gift' => self::gift($png),
            'gift-height' => self::giftHeight(),
            'download' => self::download($png),
            'download-height' => self::downloadHeight(),
            'calendar' => self::calendar(),
            'calendar-height' => self::calendarHeight(),
            'large' => self::large(),
            'large-height' => self::largeHeight(),
            'form-width' => self::formWidth(),
            'form-height' => self::formHeight(),
            default => throw new InvalidArgumentException('Unknown family '.$family),
        };
    }

    /**
     * @return array<string, LabelSheet>
     */
    private static function sheets(): array
    {
        return [
            'l30' => LabelSheet::letter30(),
            'l10' => LabelSheet::letter10(),
            'l6' => LabelSheet::letter6(),
            'a21' => LabelSheet::a4x21(),
            'a8' => LabelSheet::a4x8(),
            'tiny' => LabelSheet::of(PageSize::of(300, 200), 2, 2, 150, 100),
            'slim' => LabelSheet::of(PageSize::of(300, 200), 1, 3, 90, 40, 5, 7, 0, 11),
            'high' => LabelSheet::of(PageSize::of(300, 400), 2, 1, 120, 300, 13, 17, 9),
            'one' => LabelSheet::single(PageSize::of(252, 144)),
        ];
    }

    /**
     * @return iterable<string, Document>
     */
    private static function address(): iterable
    {
        foreach (self::sheets() as $key => $sheet) {
            foreach ([10.0, 14.0] as $size) {
                yield $key.'/'.$size => new AddressLabels($sheet, size: $size)->render([
                    new AddressLabel(['Ann Lee', ' 1 Main Street ', '', 'Springfield', 'Country']),
                    new AddressLabel([self::LONG, self::LONG, 'b', 'c', 'd', 'e', 'f', 'g']),
                    new AddressLabel(['One line']),
                ]);
            }
        }
    }

    /**
     * @return iterable<string, Document>
     */
    private static function product(Image $png): iterable
    {
        foreach (self::sheets() as $key => $sheet) {
            yield $key => new ProductLabels($sheet)->render([
                new ProductLabel('Shirt', '$9', 'SKU1', 'Blue'),
                new ProductLabel(self::LONG, self::LONG, self::LONG, self::LONG),
                new ProductLabel('Plain', '$1'),
                new ProductLabel('One two three four five six seven eight nine', '$12.50', 'SKU-2', 'Detail', $png),
                new ProductLabel('Coded', '$3', code: $png),
            ]);
        }
    }

    /**
     * @return iterable<string, Document>
     */
    private static function badge(Image $png): iterable
    {
        foreach (self::sheets() as $key => $sheet) {
            yield $key => new NameBadges('Convention', $sheet)->render([
                new Badge('Ann', 'she/her', 'Org'),
                new Badge(self::LONG, self::LONG, self::LONG),
                new Badge('Bo'),
                new Badge('Maximilien-Alexandre de Montmorency', 'Subtitle', 'Organisation'),
            ]);
            yield $key.'/logo' => new NameBadges(self::LONG, $sheet, accent: Color::rgb(200, 30, 30), logo: $png)->render([
                new Badge('Ann', 'she', 'Org'),
                new Badge(self::LONG, self::LONG, self::LONG),
            ]);
        }
    }

    /**
     * @return iterable<string, Document>
     */
    private static function shelf(Image $png): iterable
    {
        foreach (self::sheets() as $key => $sheet) {
            yield $key => new ShelfLabels($sheet)->render([
                new ShelfLabel('Dice', '$5', '$1 / die', 'D7', 'A3'),
                new ShelfLabel(self::LONG, '$1234567.89', self::LONG, self::LONG, self::LONG),
                new ShelfLabel('Plain', '$2'),
                new ShelfLabel('Coded', '$3', sku: 'S', code: $png),
                new ShelfLabel('Located', '$3', location: 'Aisle 4'),
                new ShelfLabel('Dear', self::LONG, '$1 / die'),
            ]);
        }
    }

    /**
     * @return iterable<string, Document>
     */
    private static function gift(Image $png): iterable
    {
        foreach (self::sheets() as $key => $sheet) {
            foreach ([false, true] as $cut) {
                yield $key.($cut ? '/cut' : '') => new GiftCards('Gift card', $sheet, cutLines: $cut)->render(self::giftCards($png));
            }

            yield $key.'/logo' => new GiftCards(self::LONG, $sheet, logo: $png, codeLabel: 'Numéro', pinLabel: 'NIP', validUntilLabel: 'Valide')->render(self::giftCards($png));
        }
    }

    /**
     * @return iterable<string, string>
     */
    private static function giftHeight(): iterable
    {
        foreach (['$5', 'A very long amount that does not fit a card, whatever its size'] as $amount) {
            foreach (range(0, 15) as $combination) {
                $content = '';

                for ($height = 40.0; $height <= 110.0; $height += 0.5) {
                    $card = new GiftCard($amount, 'GC1', 0 !== ($combination & 1) ? '12' : null, 0 !== ($combination & 2) ? 'For Ann' : null, 0 !== ($combination & 4) ? 'Happy birthday to you' : null, 0 !== ($combination & 8) ? 'Dec' : null);
                    $content .= new PdfReader(new GiftCards('G', LabelSheet::single(PageSize::of(300, $height)), cutLines: false)->render([$card])->render())->contents();
                }

                yield $amount.'/'.$combination => $content;
            }
        }
    }

    /**
     * @return iterable<string, Document>
     */
    private static function download(Image $png): iterable
    {
        foreach (self::sheets() as $key => $sheet) {
            foreach ([false, true] as $cut) {
                yield $key.($cut ? '/cut' : '') => new DownloadCards('Enter the code at the address below', 'example.com/download', $sheet, cutLines: $cut)->render(self::downloadCards($png));
            }

            yield $key.'/logo' => new DownloadCards(self::LONG, self::LONG, $sheet, logo: $png)->render(self::downloadCards($png));
        }
    }

    /**
     * @return iterable<string, string>
     */
    private static function downloadHeight(): iterable
    {
        foreach ([false, true] as $image) {
            $content = '';

            for ($height = 20.0; $height <= 140.0; $height += 0.5) {
                $card = new DownloadCard('Book', 'K7', 'PDF', $image ? Image::fromBytes(PngBuilder::build(2, 2, 0, 8, array_fill(0, 2, str_repeat("\x80", 2)))) : null);
                $content .= new PdfReader(new DownloadCards('Enter the code at the address below', 'ex.com', LabelSheet::single(PageSize::of(200, $height)), cutLines: false)->render([$card])->render())->contents();
            }

            yield $image ? 'image' : 'text' => $content;
        }
    }

    /**
     * @return iterable<string, Document>
     */
    private static function calendar(): iterable
    {
        foreach ([[2026, 2], [2026, 3], [2026, 11], [2028, 2], [2026, 8]] as [$year, $month]) {
            $events = [1 => ['A'], 3 => ['Game', 'Night', 'Late', 'Later', 'Latest', self::LONG], 15 => array_fill(0, 12, 'Event'), 28 => [self::LONG]];

            foreach ([1, 2, 4, 7] as $first) {
                foreach (['letter' => null, 'small' => PageSize::of(400, 300), 'tall' => PageSize::of(300, 700)] as $key => $page) {
                    yield \sprintf('%d-%d/%d/%s', $year, $month, $first, $key) => new Calendar(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], $first, $page)->render([new CalendarMonth($year, $month, 'Month '.$year, $events)]);
                }
            }
        }

        yield 'long-title' => new Calendar(['M', 'T', 'W', 'T', 'F', 'S', 'S'], 1, PageSize::of(200, 300), accent: Color::rgb(0, 128, 0))->render([new CalendarMonth(2026, 5, self::LONG), new CalendarMonth(2026, 6, 'Short', [30 => ['x']])]);
        yield 'long-weekdays' => new Calendar(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 1, PageSize::of(200, 300))->render([new CalendarMonth(2026, 5, 'May')]);
    }

    /**
     * @return iterable<string, string>
     */
    private static function calendarHeight(): iterable
    {
        foreach ([2, 3, 11] as $month) {
            $content = '';

            for ($height = 150.0; $height <= 420.0; $height += 0.5) {
                $content .= new PdfReader(new Calendar(['M', 'T', 'W', 'T', 'F', 'S', 'S'], 1, PageSize::of(300, $height))->render([new CalendarMonth(2026, $month, 'M', [2 => ['One', 'Two', 'Three', 'Four', 'Five', 'Six']])])->render())->contents();
            }

            yield (string) $month => $content;
        }
    }

    /**
     * @return iterable<string, Document>
     */
    private static function large(): iterable
    {
        $paragraph = str_repeat('Some words that flow over lines and pages. ', 40);

        foreach ([[18.0, 24.0, 72.0], [20.0, 20.0, 18.0], [36.0, 40.0, 30.0], [18.0, 30.0, 50.0], [24.0, 24.0, 100.0]] as [$size, $heading, $margin]) {
            foreach ([true, false] as $numbers) {
                foreach (['letter' => null, 'small' => PageSize::of(300, 400), 'square' => PageSize::of(400, 400)] as $key => $page) {
                    yield \sprintf('%s/%s/%s/%s', $size, $heading, $margin.($numbers ? '' : '-n'), $key) => new LargePrint($page, size: $size, headingSize: $heading, margin: $margin, pageNumbers: $numbers)->render([
                        LargePrintBlock::heading('First heading'),
                        LargePrintBlock::paragraph($paragraph),
                        LargePrintBlock::heading('Second heading that is a little longer than the first one is'),
                        LargePrintBlock::paragraph('Short.'),
                        LargePrintBlock::heading('Third'),
                        LargePrintBlock::paragraph($paragraph),
                        LargePrintBlock::heading('Fourth'),
                        LargePrintBlock::heading('Fifth'),
                        LargePrintBlock::paragraph($paragraph.$paragraph),
                    ]);
                }
            }
        }

        yield 'default' => new LargePrint()->render([LargePrintBlock::heading('Title'), LargePrintBlock::paragraph($paragraph)]);
        yield 'tall-heading-first' => new LargePrint(PageSize::of(300, 300), size: 36, headingSize: 36, margin: 30)->render([LargePrintBlock::heading(str_repeat('Heading words ', 12)), LargePrintBlock::paragraph('Text.')]);
    }

    /**
     * @return iterable<string, string>
     */
    private static function largeHeight(): iterable
    {
        foreach ([[18.0, 18.0], [20.0, 24.0], [36.0, 40.0]] as [$size, $heading]) {
            $content = '';

            for ($height = 36.0 + 4 * $heading; $height <= 330.0; $height += 0.5) {
                $content .= new PdfReader(new LargePrint(PageSize::of(300, $height), size: $size, headingSize: $heading, margin: 18, pageNumbers: false)->render([
                    LargePrintBlock::paragraph('One two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen'),
                    LargePrintBlock::heading('A heading that spans two lines of the page'),
                    LargePrintBlock::paragraph('Short.'),
                    LargePrintBlock::heading('Last'),
                    LargePrintBlock::paragraph('Final words here and there'),
                ])->render())->contents();
            }

            yield $size.'/'.$heading => $content;
        }
    }

    /**
     * @return iterable<string, string>
     */
    private static function formWidth(): iterable
    {
        foreach ([1.0, 10.0] as $size) {
            $content = '';

            for ($width = 1.0; $width <= 90.0; $width += 0.5) {
                $content .= self::field(new TextField('n', 0, 0, $width, 60, 'iiiiiiiiiiiiii one two three iiiiiii four'."\nfive six", $size, multiline: true));
            }

            yield (string) $size => $content;
        }
    }

    /**
     * @return iterable<string, string>
     */
    private static function formHeight(): iterable
    {
        foreach ([1.0, 8.0, 10.0] as $size) {
            $content = '';

            for ($height = 1.0; $height <= 120.0; $height += 0.5) {
                $content .= self::field(new TextField('n', 0, 0, 25, $height, 'one two three four five six seven eight nine ten', $size, multiline: true));
            }

            yield (string) $size => $content;
        }
    }

    private static function field(TextField $field): string
    {
        $document = new Document(compress: false);
        $document->addPage();
        $document->field($field);

        $reader = new PdfReader($document->render());

        return PdfReader::data($reader->find('/BBox [0 0 '.Number::format($field->width).' '.Number::format($field->height).']'));
    }

    /**
     * @return list<GiftCard>
     */
    private static function giftCards(Image $png): array
    {
        return [
            new GiftCard('$50', 'GC1'),
            new GiftCard('$50', 'GC2', '1234'),
            new GiftCard('$50', 'GC3', validUntil: '2027-01-01'),
            new GiftCard('$50', 'GC4', '1234', 'For Ann', 'Happy birthday, may your dice roll high on every single throw you ever make.', '2027-01-01'),
            new GiftCard('$50', 'GC5', recipient: 'For Ann'),
            new GiftCard('$50', 'GC6', message: 'Happy birthday'),
            new GiftCard('$50', 'GC7', '12', 'For Ann', 'Hi', 'Dec', $png),
            new GiftCard('$1,000,000.00 and a very long amount', 'GC-A-VERY-LONG-CODE-THAT-WILL-NOT-FIT-IN-ANY-CARD', '9999', self::LONG, self::LONG, self::LONG, $png),
            new GiftCard('$20', 'GC8', str_repeat('1234567890', 8), validUntil: str_repeat('2027-01-01 ', 8)),
            new GiftCard('$20', 'GC9', str_repeat('1234567890', 8), 'For Ann', 'Hi', str_repeat('2027-01-01 ', 8), $png),
        ];
    }

    /**
     * @return list<DownloadCard>
     */
    private static function downloadCards(Image $png): array
    {
        return [
            new DownloadCard('Book', 'K7'),
            new DownloadCard('Book', 'K7', 'PDF'),
            new DownloadCard('Book', 'K7', codeImage: $png),
            new DownloadCard(self::LONG, self::LONG, self::LONG, $png),
            new DownloadCard('Another', 'ABCDEFGH-1234', 'Edition 2', $png),
        ];
    }
}
