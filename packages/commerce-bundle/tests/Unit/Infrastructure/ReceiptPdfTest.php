<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceBundle\Infrastructure\Pdf\ReceiptPdf;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\PageSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReceiptPdf::class)]
final class ReceiptPdfTest extends TestCase
{
    /**
     * A description too long for its column goes on below, and never runs into the quantity.
     */
    public function testALongDescriptionGoesOnBelowItsLine(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-5', $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'SET'), 'A complete set of seven polyhedral dice with a velvet bag and a printed guide to every roll', Money::of(500, $cad), 'standard'), 2, Money::of(500, $cad));
        $order->place(new DateTimeImmutable('2026-11-05'));

        $output = $this->readable(new ReceiptPdf()->render(Receipt::issue('R-5', $order, Issuer::of('Shop'), new DateTimeImmutable('2026-11-05'))));

        self::assertSame(1, preg_match('/BT \/F2 10 Tf 54 (\S+) Td \((A complete set[^)]*)\) Tj ET\nBT \/F2 10 Tf (\S+) \1 Td \(2\) Tj ET/', $output, $first));
        self::assertSame('A complete set of seven polyhedral dice with a velvet bag', $first[2], 'The first line is as long as the column allows at the size it is printed in.');
        self::assertLessThanOrEqual(612 - 54 - 225 - 10 - 54, StandardFont::Helvetica->width($first[2], 10));
        preg_match_all('/BT \/F2 10 Tf 54 \S+ Td \(([^)]*)\) Tj ET/', $output, $all);
        $start = array_search($first[2], $all[1], true);
        self::assertIsInt($start);
        self::assertSame('A complete set of seven polyhedral dice with a velvet bag and a printed guide to every roll', implode(' ', \array_slice($all[1], $start, 3)));
    }

    /**
     * Each option of a line is printed below it, stood in, its label and its value: a value still to draw says so.
     */
    public function testTheOptionsOfALineArePrintedBelowIt(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-6', $cad);
        $options = OptionSet::of(
            OptionDefinition::text('name', 'Name', TextConstraints::of(200)),
            OptionDefinition::choice('stone', 'Stone', [OptionChoice::of('marble', 'Marble')]),
            OptionDefinition::draw('loot', 'Loot', 'dice'),
        );
        $shirt = Sellable::of(SellableReference::of('product', 'SHIRT'), 'Shirt', Money::of(2_500, $cad), 'standard', $options);
        $order->add($shirt, 1, Money::of(2_500, $cad), LineConfiguration::of(['name' => OptionValue::chosen('Bob'), 'stone' => OptionValue::chosen('marble')]));
        $order->add($shirt, 1, Money::of(2_500, $cad), LineConfiguration::of(['name' => OptionValue::chosen(trim(str_repeat('ab ', 40)))]));
        $order->add(Sellable::of(SellableReference::of('product', 'MUG'), 'Mug', Money::of(1_000, $cad), 'standard'), 1, Money::of(1_000, $cad));
        $order->place(new DateTimeImmutable('2026-11-07'));

        $receipt = Receipt::issue('R-6', $order, Issuer::of('Shop'), new DateTimeImmutable('2026-11-07'));

        $output = $this->readable(new ReceiptPdf()->render($receipt));
        preg_match_all('/BT \/F2 10 Tf (\S+) \S+ Td \(([^)]*)\) Tj ET/', $output, $texts);
        $start = array_search('Shirt', $texts[2], true);
        self::assertIsInt($start);
        self::assertSame(['Shirt', '1', '25.00', '25.00', 'Loot: drawn after payment', 'Name: Bob', 'Stone: Marble'], \array_slice($texts[2], $start, 7));
        self::assertSame(['54', '66', '66', '66'], [$texts[1][$start], ...\array_slice($texts[1], $start + 4, 3)]);

        $long = array_search('Shirt', \array_slice($texts[2], $start + 1), true);
        self::assertIsInt($long);
        // The width left of the quantity column, less the indent, at the size printed: 16, 18 then 6 words.
        $words = static fn (int $count): string => trim(str_repeat('ab ', $count));
        self::assertSame(['Name: '.$words(16), $words(18), $words(6)], \array_slice($texts[2], $start + 1 + $long + 5, 3));
        self::assertLessThanOrEqual(612 - 54 - 225 - 10 - 54 - 12, StandardFont::Helvetica->width('Name: '.$words(16), 10));
        self::assertGreaterThan(612 - 54 - 225 - 10 - 54 - 12, StandardFont::Helvetica->width('Name: '.$words(17), 10));
        self::assertSame('Mug', $texts[2][$start + 1 + $long + 8]);

        $translated = $this->readable(new ReceiptPdf(['option' => '%s : %s', 'to_draw' => 'tiré après le paiement'])->render($receipt));
        self::assertStringContainsString('(Loot : tir', $translated);
        self::assertStringContainsString('(Name : Bob) Tj', $translated);
    }

    public function testAReceiptIsPrinted(): void
    {
        $raw = new ReceiptPdf()->render($this->receipt());
        $output = $this->readable($raw);

        foreach (['(La boutique) Tj', '(12 rue Principale) Tj', '(GST 123456789RT0001) Tj', '(Receipt R-000001) Tj', '(Date 2026-11-03) Tj', '(Order A-1) Tj', '(Amounts in CAD) Tj', '(Description) Tj', '(Tea) Tj', '(3) Tj', '(5.00) Tj', '(15.00) Tj', '(Subtotal) Tj', '(Delivery) Tj', '(10.00) Tj', '(GST) Tj', '(0.75) Tj', '(Total) Tj', '(25.75) Tj'] as $text) {
            self::assertStringContainsString($text, $output, $text);
        }

        self::assertStringNotContainsString('(0.05)', $output);
        self::assertStringContainsString('(0.10) Tj', $output);
        self::assertStringNotContainsString('Credits', $output);
        self::assertStringEqualsFile(__DIR__.'/Fixtures/receipt.pdf', $raw);
    }

    /**
     * At a fair the price on the tag is the price paid: the line and the total are the prices, and the taxes they
     * included come after the total, each by its code and then their sum.
     */
    public function testTaxesIncludedInThePricesComeAfterTheTotal(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-3', $cad);
        $book = SellableReference::of('product', 'BOOK');
        $order->add(Sellable::of($book, 'Book', Money::of(1_000, $cad), 'standard'), 1, Money::of(1_000, $cad));
        $order->replaceAdjustments(AdjustmentType::Tax, [Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(44, $cad), $book, true), Adjustment::of(AdjustmentType::Tax, 'QST', Money::of(87, $cad), $book, true)]);
        $order->place(new DateTimeImmutable('2026-11-04'));

        $receipt = Receipt::issue('R-3', $order, Issuer::of('Shop'), new DateTimeImmutable('2026-11-04'));

        $output = $this->readable(new ReceiptPdf(['included' => '%s incluse', 'taxes_included' => 'Taxes incluses'])->render($receipt));
        $total = strpos($output, '(Total) Tj');

        self::assertSame(['GST' => 44, 'QST' => 87], array_map(static fn (Money $tax): int => $tax->minorUnits, $receipt->includedTaxes()));
        self::assertSame(['GST' => -44, 'QST' => -87], array_map(static fn (Money $tax): int => $tax->minorUnits, Receipt::credit('N-3', $receipt, [], new DateTimeImmutable('2026-11-05'))->includedTaxes()));
        self::assertNotFalse($total);
        self::assertSame(4, substr_count($output, '(10.00) Tj'));
        self::assertStringNotContainsString('(GST) Tj', $output);
        self::assertGreaterThan($total, strpos($output, '(GST incluse) Tj'));
        self::assertGreaterThan(strpos($output, '(GST incluse) Tj'), strpos($output, '(QST incluse) Tj'));
        self::assertGreaterThan(strpos($output, '(QST incluse) Tj'), strpos($output, '(Taxes incluses) Tj'));
        self::assertStringContainsString('(0.44) Tj', $output);
        self::assertStringContainsString('(0.87) Tj', $output);
        self::assertStringContainsString('(1.31) Tj', $output);
        self::assertSame([], $this->receiptWithoutIncludedTaxes()->includedTaxes());
        self::assertSame(['%s included', 'Taxes included'], [ReceiptPdf::LABELS['included'], ReceiptPdf::LABELS['taxes_included']]);
    }

    public function testACreditNoteIsNegativeAndTranslated(): void
    {
        $receipt = $this->receipt();
        $note = Receipt::credit('N-000001', $receipt, [], new DateTimeImmutable('2026-11-04'));

        $raw = new ReceiptPdf(['credit_note' => 'Note de crédit', 'credits' => 'Crédite', 'unknown' => 'x'], PageSize::a4())->render($note);
        $output = $this->readable($raw);

        foreach (["(Note de cr\xE9dit N-000001) Tj", "(Cr\xE9dite R-000001) Tj", '(-15.00) Tj', '(-10.00) Tj', '(-0.75) Tj', '(-25.75) Tj', '/MediaBox [0 0 595 842]'] as $text) {
            self::assertStringContainsString($text, $output, $text);
        }

        self::assertStringNotContainsString('(unknown)', $output);
        self::assertStringEqualsFile(__DIR__.'/Fixtures/credit-note.pdf', $raw);
        self::assertSame(ReceiptPdf::LABELS['total'], 'Total');
    }

    public function testALongReceiptTakesSeveralPagesWithTheirHeader(): void
    {
        $order = Order::open('A-2', Currency::of('CAD'));

        for ($i = 1; $i <= 60; ++$i) {
            $order->add(Sellable::of(SellableReference::of('product', 'P'.$i), 'Product '.$i, Money::of(100, Currency::of('CAD')), 'standard'), 1, Money::of(100, Currency::of('CAD')));
        }

        $order->place(new DateTimeImmutable('2026-11-03'));
        $raw = new ReceiptPdf()->render(Receipt::issue('R-2', $order, Issuer::of('Shop'), new DateTimeImmutable('2026-11-03')));
        $output = $this->readable($raw);

        self::assertStringContainsString('/Count 2', $output);
        self::assertSame(2, substr_count($output, '(Description) Tj'));
        self::assertStringContainsString('(Product 60) Tj', $output);
        self::assertStringEqualsFile(__DIR__.'/Fixtures/long-receipt.pdf', $raw);
    }

    public function testAReceiptCanBePrintedInAnEmbeddedFont(): void
    {
        $font = TrueTypeFont::fromFile(__DIR__.'/Fixtures/receipt-font.ttf');
        $output = $this->readable(new ReceiptPdf([], null, $font, $font)->render($this->receipt()));

        self::assertStringContainsString('/Subtype /Type0 /BaseFont /', $output);
        self::assertStringNotContainsString('/Helvetica', $output);
        self::assertMatchesRegularExpression('/BT \/F1 16 Tf 54 738 Td <[0-9A-F]+> Tj ET/', $output, 'The issuer is printed in the font given for bold.');
        self::assertStringContainsString('beginbfchar', $output);
    }

    public function testAnArchivedReceiptIsPdfA(): void
    {
        $font = TrueTypeFont::fromFile(__DIR__.'/Fixtures/receipt-font.ttf');
        $output = self::readable(new ReceiptPdf([], null, $font, $font, Conformance::PdfA2u)->render($this->receipt()));

        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1 ', $output);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $output);
        self::assertStringContainsString('<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Receipt R-000001</rdf:li></rdf:Alt></dc:title>', $output);
        self::assertStringContainsString('<dc:creator><rdf:Seq><rdf:li>La boutique</rdf:li></rdf:Seq></dc:creator>', $output);
        self::assertStringContainsString('<xmp:CreateDate>2026-11-03T10:00:00+00:00</xmp:CreateDate>', $output);
    }

    public function testAnArchivedReceiptNeedsEmbeddedFonts(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('A PDF/A-2u document embeds its fonts; Helvetica-Bold cannot be used, a TrueType font can.');

        new ReceiptPdf(conformance: Conformance::PdfA2u)->render($this->receipt());
    }

    public function testTheDocumentCarriesTheReceiptsTitleIssuerAndDate(): void
    {
        $output = new ReceiptPdf(['credit_note' => 'Note de crédit'])->render(Receipt::credit('N-000001', $this->receipt(), [], new DateTimeImmutable('2026-11-04 08:00:00')));

        self::assertStringContainsString('<< /Title <FEFF004E006F0074006500200064006500200063007200E90064006900740020004E002D003000300030003000300031> /Author (La boutique) /Producer (phil/paged-media) /CreationDate (D:20261104080000Z) >>', $output);
    }

    public function testALineOnTheBottomMarginStaysOnItsPage(): void
    {
        $order = Order::open('A-3', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'P'), 'Product 1', Money::of(100, Currency::of('CAD')), 'standard'), 1, Money::of(100, Currency::of('CAD')));
        $order->place(new DateTimeImmutable('2026-11-03'));

        $output = $this->readable(new ReceiptPdf([], PageSize::of(612, 220))->render(Receipt::issue('R-3', $order, Issuer::of('Shop'), new DateTimeImmutable('2026-11-03'))));

        self::assertStringContainsString('BT /F2 10 Tf 54 54 Td (Product 1) Tj ET', $output);
    }

    private function receiptWithoutIncludedTaxes(): Receipt
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-4', $cad);
        $tea = SellableReference::of('product', 'TEA');
        $order->add(Sellable::of($tea, 'Tea', Money::of(500, $cad), 'standard'), 1, Money::of(500, $cad));
        $order->replaceAdjustments(AdjustmentType::Tax, [Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(25, $cad), $tea)]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Included', Money::of(5, $cad), null, true)]);
        $order->place(new DateTimeImmutable('2026-11-04'));

        return Receipt::issue('R-4', $order, Issuer::of('Shop'), new DateTimeImmutable('2026-11-04'));
    }

    private function receipt(): Receipt
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);
        $tea = SellableReference::of('product', 'TEA');
        $order->add(Sellable::of($tea, 'Tea', Money::of(500, $cad), 'standard'), 3, Money::of(500, $cad));
        $order->replaceAdjustments(AdjustmentType::Tax, [Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(75, $cad), $tea), Adjustment::of(AdjustmentType::Tax, 'Included', Money::of(10, $cad), $tea, true)]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(1_000, $cad)), Adjustment::of(AdjustmentType::Shipping, 'Included', Money::of(5, $cad), null, true)]);
        $order->place(new DateTimeImmutable('2026-11-03 10:00:00'));

        return Receipt::issue('R-000001', $order, Issuer::of('La boutique', ['12 rue Principale'], ['GST' => '123456789RT0001']), new DateTimeImmutable('2026-11-03 10:00:00'));
    }

    /**
     * The document with each compressed stream replaced by its text, after checking that the cross-reference table
     * points at each object and that each stream is as long as it says.
     */
    private function readable(string $pdf): string
    {
        self::assertSame(1, preg_match('/startxref\n(\d+)\n/', $pdf, $start));
        $xref = (int) $start[1];
        self::assertSame("xref\n", substr($pdf, $xref, 5));
        preg_match_all('/^(\d{10}) 00000 n $/m', substr($pdf, $xref), $offsets);
        self::assertNotSame([], $offsets[1]);

        foreach ($offsets[1] as $index => $offset) {
            self::assertSame(($index + 1).' 0 obj', substr($pdf, (int) $offset, \strlen(($index + 1).' 0 obj')));
        }

        return $this->inflate($pdf);
    }

    private function inflate(string $pdf): string
    {
        $readable = '';
        $at = 0;

        while (1 === preg_match('/\/Filter \/FlateDecode \/Length (\d+) >>\nstream\n/', $pdf, $match, \PREG_OFFSET_CAPTURE, $at)) {
            $start = $match[0][1] + \strlen($match[0][0]);
            $data = substr($pdf, $start, (int) $match[1][0]);
            self::assertSame("\nendstream", substr($pdf, $start + \strlen($data), 10), 'A stream is as long as it says.');
            $readable .= substr($pdf, $at, $start - $at).gzuncompress($data);
            $at = $start + \strlen($data);
        }

        return $readable.substr($pdf, $at);
    }
}
