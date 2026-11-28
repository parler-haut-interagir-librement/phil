<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\ReceiptEmail\Domain\Donation\GiftKind;
use Phil\ReceiptEmail\Domain\ValueObject\Donation;
use Phil\ReceiptEmail\Domain\ValueObject\Party;
use Phil\ReceiptEmail\Infrastructure\Pdf\PagedMediaDonationReceiptPdf;
use Phil\ReceiptEmail\Tests\Support\Donations;
use Phil\ReceiptEmail\Tests\Support\PdfText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PagedMediaDonationReceiptPdf::class)]
final class PagedMediaDonationReceiptPdfTest extends TestCase
{
    /**
     * The addresses are wrapped to the width of the page, which reads a run of spaces as one.
     */
    public function testEveryStatementOfAnOfficialReceiptIsPrinted(): void
    {
        $pdf = new PagedMediaDonationReceiptPdf()->render(Donations::receipt(), 'en');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame([
            'The Reading Circle',
            '8 Library Lane',
            'Montréal QC H2X 2Z9',
            'Registration number: 123456789 RR 0001',
            'Official receipt for income tax purposes',
            'Serial number: D-2026-000017',
            'Date issued: 2026-11-20',
            'Location issued: Montréal',
            'Date gift received: 2026-11-18',
            'Donor',
            'Ada B. Lovelace',
            '1 Analytical Road',
            'Québec QC G1R 4P5',
            'Amount of the gift',
            '150.00 CAD',
            'Amount of the advantage',
            '40.00 CAD',
            'Advantage: A ticket to the gala dinner',
            'Eligible amount of the gift',
            '110.00 CAD',
            'Authorized signature: Grace Hopper, treasurer',
            'Canada Revenue Agency: canada.ca/charities-giving',
        ], PdfText::texts($pdf));
        self::assertStringContainsString('/Title (Official receipt for income tax purposes D-2026-000017)', PdfText::readable($pdf));
        self::assertStringContainsString('/Author (The Reading Circle)', PdfText::readable($pdf));
        self::assertStringEqualsFile(__DIR__.'/Fixtures/donation-receipt.pdf', $pdf, 'Every text at its place, in its font and size.');
    }

    public function testAGiftOfPropertyIsPrintedInFrench(): void
    {
        $texts = PdfText::texts(new PagedMediaDonationReceiptPdf()->render(Donations::receipt(Donations::property()), 'fr_CA'));

        self::assertSame([
            'The Reading Circle',
            '8 Library Lane',
            'Montréal QC H2X 2Z9',
            'Numéro d’enregistrement : 123456789 RR 0001',
            'Reçu officiel aux fins de l’impôt sur le revenu',
            'Numéro de série : D-2026-000017',
            'Date de délivrance : 2026-11-20',
            'Lieu de délivrance : Montréal',
            'Année de réception du don : 2026',
            'Donateur',
            'Ada B. Lovelace',
            '1 Analytical Road',
            'Québec QC G1R 4P5',
            'Description du don : A first edition of a book on the analytical engine',
            'Évaluateur',
            'Charles Babbage, appraiser',
            '2 Difference Street',
            'Laval QC H7N 1A1',
            'Montant du don',
            '1200.00 CAD',
            'Montant de l’avantage',
            '0.00 CAD',
            'Montant admissible du don',
            '1200.00 CAD',
            'Signature autorisée : Grace Hopper, treasurer',
            'Agence du revenu du Canada : canada.ca/charities-giving',
        ], $texts);
    }

    public function testTheLabelsOfALocaleOrALanguageMayBeReplaced(): void
    {
        $printer = new PagedMediaDonationReceiptPdf(['en' => ['donor' => 'Giver'], 'en_GB' => ['donor' => 'Benefactor'], 'fr' => ['donor' => 'Donatrice']]);

        self::assertContains('Giver', PdfText::texts($printer->render(Donations::receipt(), 'en_CA')));
        self::assertContains('Benefactor', PdfText::texts($printer->render(Donations::receipt(), 'en_GB')));
        self::assertContains('Donatrice', PdfText::texts($printer->render(Donations::receipt(), 'fr')));
        self::assertContains('Lieu de délivrance : Montréal', PdfText::texts($printer->render(Donations::receipt(), 'fr')));
        self::assertContains('Donor', PdfText::texts($printer->render(Donations::receipt(), 'de')));
    }

    public function testALongReceiptGoesOnOverPages(): void
    {
        $pdf = new PagedMediaDonationReceiptPdf(size: PageSize::a5(), signature: Image::fromBytes(self::signature()))->render(Donations::receipt(self::long()), 'en');

        self::assertGreaterThan(1, preg_match_all('/\/Type \/Page\b/', $pdf));
        preg_match_all('/BT \/F\d+ [\d.]+ Tf [\d.]+ (-?[\d.]+) Td/', PdfText::readable($pdf), $baselines);
        self::assertGreaterThan(10, \count($baselines[1]));

        foreach ($baselines[1] as $baseline) {
            self::assertGreaterThanOrEqual(54, (float) $baseline, 'Nothing is printed in the bottom margin.');
        }

        self::assertContains('Signatory', array_map(static fn (string $text): string => substr($text, -9), PdfText::texts($pdf)));
        self::assertStringEqualsFile(__DIR__.'/Fixtures/donation-receipt-long.pdf', $pdf, 'Each page break, and the signature on the page it fits.');
    }

    public function testALineThatReachesTheBottomMarginStaysOnItsPage(): void
    {
        // On a page 444 points high, the last line falls on the margin itself, 54 points from the bottom.
        $pdf = PdfText::readable(new PagedMediaDonationReceiptPdf(size: PageSize::of(612, 444))->render(Donations::receipt(), 'en'));
        preg_match_all('/BT \/F\d+ [\d.]+ Tf [\d.]+ (-?[\d.]+) Td/', $pdf, $baselines);

        self::assertSame(1, preg_match_all('/\/Type \/Page\b/', $pdf));
        self::assertContains('54', $baselines[1]);
    }

    public function testASignatureThatReachesTheBottomMarginStaysOnItsPage(): void
    {
        // On a page 438 points high, the signature's foot falls on the margin itself.
        $pdf = PdfText::readable(new PagedMediaDonationReceiptPdf(size: PageSize::of(612, 438), signature: Image::fromBytes(self::signature()))->render(Donations::receipt(), 'en'));

        self::assertStringContainsString('q 72 0 0 36 54 54 cm /Im1 Do Q', $pdf);
    }

    public static function long(): Donation
    {
        $address = array_fill(0, 10, 'A line of an address');
        $cad = Currency::of('CAD');

        return Donation::of(Party::of('Charity', $address), '123456789 RR 0001', 'Montréal', Party::of('Donor', $address), new DateTimeImmutable('2026-11-18'), GiftKind::NonCash, Money::of(10_000, $cad), Money::of(100, $cad), str_repeat('An advantage described at length. ', 14), str_repeat('A gift described at length. ', 17), Party::of('Appraiser', $address), 'Signatory');
    }

    public static function signature(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAAAAADRSSBZAAAAC0lEQVR4nGNgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII=', true);
    }

    public function testTheSignatureIsPrintedAboveTheSignatorysName(): void
    {
        $pdf = PdfText::readable(new PagedMediaDonationReceiptPdf(signature: Image::fromBytes(self::signature()))->render(Donations::receipt(), 'en'));

        self::assertSame(1, preg_match('/q 72 0 0 36 54 (\S+) cm \/Im1 Do Q/', $pdf, $image));
        self::assertSame(1, preg_match('/BT \/F\d+ 10 Tf 54 (\S+) Td \(Authorized signature/', $pdf, $name));
        self::assertEqualsWithDelta((float) $image[1] - 14, (float) $name[1], 0.001, 'The name is printed on the line under the image.');
    }

    public function testTheSignatureKeepsTheProportionsOfItsImage(): void
    {
        // An image twice as high as wide is drawn 18 points wide at 36 points high.
        $tall = PdfText::readable(new PagedMediaDonationReceiptPdf(signature: Image::fromBytes($this->png(1, 2)))->render(Donations::receipt(), 'en'));
        self::assertMatchesRegularExpression('/q 18 0 0 36 54 \S+ cm \/Im1 Do Q/', $tall);

        // A PNG of no height, which the image reader lets through, is drawn as if it were one pixel high.
        $flat = PdfText::readable(new PagedMediaDonationReceiptPdf(signature: Image::fromBytes($this->png(1, 0)))->render(Donations::receipt(), 'en'));
        self::assertMatchesRegularExpression('/q 36 0 0 36 54 \S+ cm \/Im1 Do Q/', $flat);
    }

    /**
     * A grey PNG of the size given, every pixel mid-grey.
     */
    private function png(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\x80", $width), max(1, $height));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0)).$chunk('IDAT', (string) gzcompress($rows)).$chunk('IEND', '');
    }
}
