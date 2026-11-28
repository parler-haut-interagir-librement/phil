<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Infrastructure;

use Phil\CommerceBundle\Infrastructure\Pdf\ReceiptPdf;
use Phil\ReceiptEmail\Domain\Exception\ReceiptNotFound;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommerceReceiptPdf;
use Phil\ReceiptEmail\Tests\Support\Commerce;
use Phil\ReceiptEmail\Tests\Support\InMemoryReceipts;
use Phil\ReceiptEmail\Tests\Support\PdfText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommerceReceiptPdf::class)]
final class CommerceReceiptPdfTest extends TestCase
{
    public function testTheReceiptIsPrintedByTheCoresOwnPdf(): void
    {
        $receipt = Commerce::receipt();
        $pdf = new CommerceReceiptPdf(new InMemoryReceipts($receipt));

        self::assertSame(new ReceiptPdf()->render($receipt), $pdf->render('R-000042', 'en'));
        self::assertContains('Receipt R-000042', PdfText::texts($pdf->render('R-000042', 'en')));
    }

    public function testThePrinterOfTheLocaleThenOfItsLanguageIsChosen(): void
    {
        $printers = [
            'fr' => new ReceiptPdf(['receipt' => 'Reçu']),
            'fr_BE' => new ReceiptPdf(['receipt' => 'Quittance']),
        ];
        $pdf = new CommerceReceiptPdf(new InMemoryReceipts(Commerce::receipt()), new ReceiptPdf(['receipt' => 'Ticket']), $printers);

        self::assertContains('Reçu R-000042', PdfText::texts($pdf->render('R-000042', 'fr_CA')));
        self::assertContains('Reçu R-000042', PdfText::texts($pdf->render('R-000042', 'fr')));
        self::assertContains('Quittance R-000042', PdfText::texts($pdf->render('R-000042', 'fr_BE')));
        self::assertContains('Ticket R-000042', PdfText::texts($pdf->render('R-000042', 'en')));
        self::assertContains('Ticket R-000042', PdfText::texts($pdf->render('R-000042', 'frr')));
    }

    public function testAnUnknownReceiptIsNotPrinted(): void
    {
        $this->expectException(ReceiptNotFound::class);
        $this->expectExceptionMessageIs('No payment receipt R-404 is found.');

        new CommerceReceiptPdf(new InMemoryReceipts())->render('R-404', 'en');
    }
}
