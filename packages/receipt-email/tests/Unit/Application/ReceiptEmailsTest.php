<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\ReceiptEmail\Application\InlineImage;
use Phil\ReceiptEmail\Application\Port\DonationReceiptPdf;
use Phil\ReceiptEmail\Application\Port\PaymentReceiptPdf;
use Phil\ReceiptEmail\Application\Port\ReceiptRenderer;
use Phil\ReceiptEmail\Application\ReceiptEmails;
use Phil\ReceiptEmail\Application\Recipient;
use Phil\ReceiptEmail\Application\RenderedReceipt;
use Phil\ReceiptEmail\Application\TransactionalCheck;
use Phil\ReceiptEmail\Domain\Exception\NotTransactional;
use Phil\ReceiptEmail\Domain\Exception\PdfUnavailable;
use Phil\ReceiptEmail\Domain\Exception\ReceiptNotFound;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts;
use Phil\ReceiptEmail\Infrastructure\EmailTemplates\EmailTemplatesRenderer;
use Phil\ReceiptEmail\Tests\Support\Commerce;
use Phil\ReceiptEmail\Tests\Support\Donations;
use Phil\ReceiptEmail\Tests\Support\FixedRecipients;
use Phil\ReceiptEmail\Tests\Support\InMemoryDonationReceipts;
use Phil\ReceiptEmail\Tests\Support\InMemoryReceipts;
use Phil\ReceiptEmail\Tests\Support\StubRenderer;
use Phil\ReceiptEmail\Tests\Support\Templates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

#[CoversClass(ReceiptEmails::class)]
#[CoversClass(ReceiptNotFound::class)]
#[CoversClass(PdfUnavailable::class)]
#[CoversClass(InlineImage::class)]
#[CoversClass(RenderedReceipt::class)]
final class ReceiptEmailsTest extends TestCase
{
    public function testThePaymentReceiptIsRenderedForItsRecipient(): void
    {
        $renderer = new StubRenderer(new RenderedReceipt('Receipt R-000042', 'Thank you', '<p><img src="cid:logo" alt="Shop"> Paid</p>', 'Paid', [new InlineImage('logo', 'PNG', 'image/png')]));
        $email = $this->emails($renderer)->payment('R-000042');

        $receipt = $renderer->messages[0]->variables['receipt'];
        self::assertIsArray($receipt);
        self::assertSame('R-000042', $receipt['number'] ?? null);
        self::assertSame('fr', $renderer->messages[0]->locale);
        self::assertSame('receipt', $renderer->messages[0]->template);
        self::assertSame('"The Shop" <shop@example.com>', $email->getFrom()[0]->toString());
        self::assertSame('"Ada" <ada@example.com>', $email->getTo()[0]->toString());
        self::assertSame('Receipt R-000042', $email->getSubject());
        self::assertSame('Paid', $email->getTextBody());
        self::assertSame('<p><img src="cid:logo" alt="Shop"> Paid</p>', $email->getHtmlBody());
        self::assertSame('auto-generated', $email->getHeaders()->getHeaderBody('Auto-Submitted'));
        self::assertFalse($email->getHeaders()->has('List-Unsubscribe'));
        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertSame('logo', $attachments[0]->getFilename());
        self::assertSame('image/png', $attachments[0]->getMediaType().'/'.$attachments[0]->getMediaSubtype());
        self::assertStringContainsString('Content-Disposition: inline', $attachments[0]->toString());
    }

    public function testThePdfIsAttachedOnlyWhenAskedFor(): void
    {
        $pdf = new class implements PaymentReceiptPdf {
            /** @var list<array{string, string}> */
            public array $printed = [];

            public function render(string $number, string $locale): string
            {
                $this->printed[] = [$number, $locale];

                return '%PDF-1.7 '.$number;
            }
        };

        $emails = $this->emails(new StubRenderer(), paymentPdf: $pdf, receipts: [Commerce::receipt(), Commerce::creditNote()]);
        self::assertSame([], $emails->payment('R-000042')->getAttachments());
        self::assertSame([], $pdf->printed);

        $attachments = $emails->payment('C-000007', true)->getAttachments();
        self::assertSame([['C-000007', 'fr']], $pdf->printed);
        self::assertCount(1, $attachments);
        self::assertSame('receipt-C-000007.pdf', $attachments[0]->getFilename());
        self::assertSame('application/pdf', $attachments[0]->getContentType());
        self::assertSame('%PDF-1.7 C-000007', $attachments[0]->getBody());
        self::assertStringContainsString('Content-Disposition: attachment', $attachments[0]->toString());
    }

    public function testTheFileNameKeepsOnlySafeCharacters(): void
    {
        $pdf = new class implements DonationReceiptPdf {
            public function render(DonationReceipt $receipt, string $locale): string
            {
                return '%PDF '.$locale;
            }
        };
        $donation = DonationReceipt::of('D/2026\\"17.a_b-c', new DateTimeImmutable('2026-11-20'), Donations::cash());
        $attachment = $this->emails(new StubRenderer(), donationPdf: $pdf, donations: [$donation])->donation('D/2026\\"17.a_b-c', true)->getAttachments()[0];

        self::assertInstanceOf(DataPart::class, $attachment);
        self::assertSame('donation-receipt-D_2026__17.a_b-c.pdf', $attachment->getFilename());
        self::assertSame('%PDF fr', $attachment->getBody());
    }

    public function testADonationReceiptIsRenderedForTheDonor(): void
    {
        $renderer = new StubRenderer();
        $email = $this->emails($renderer)->donation('D-2026-000017');

        self::assertSame('donation-receipt', $renderer->messages[0]->template);
        $receipt = $renderer->messages[0]->variables['receipt'];
        self::assertIsArray($receipt);
        self::assertSame('110.00', $receipt['eligible_amount'] ?? null);
        self::assertSame('Paid', $email->getTextBody());
        self::assertSame([], $email->getAttachments());
    }

    public function testAPdfAskedForWithoutAPrinterIsRefused(): void
    {
        $emails = $this->emails(new StubRenderer());

        try {
            $emails->payment('R-000042', true);
            self::fail('No PaymentReceiptPdf is given.');
        } catch (PdfUnavailable $pdfUnavailable) {
            self::assertSame('The PDF of a payment receipt is asked for, and no PaymentReceiptPdf is given.', $pdfUnavailable->getMessage());
        }

        $this->expectException(PdfUnavailable::class);
        $this->expectExceptionMessageIs('The PDF of a donation receipt is asked for, and no DonationReceiptPdf is given.');

        $emails->donation('D-2026-000017', true);
    }

    public function testAReceiptNotFoundIsRefused(): void
    {
        $emails = $this->emails(new StubRenderer());

        try {
            $emails->payment('R-404');
            self::fail('R-404 is not found.');
        } catch (ReceiptNotFound $receiptNotFound) {
            self::assertSame('No payment receipt R-404 is found.', $receiptNotFound->getMessage());
        }

        $this->expectExceptionMessageIs('No donation receipt D-404 is found.');

        $emails->donation('D-404');
    }

    public function testAReceiptWithoutRecipientIsRefused(): void
    {
        $emails = $this->emails(new StubRenderer(), recipient: null);

        try {
            $emails->payment('R-000042');
            self::fail('Nobody receives it.');
        } catch (ReceiptNotFound $receiptNotFound) {
            self::assertSame('The receipt R-000042 has no recipient to send it to.', $receiptNotFound->getMessage());
        }

        $this->expectExceptionMessageIs('The receipt D-2026-000017 has no recipient to send it to.');

        $emails->donation('D-2026-000017');
    }

    public function testAReceiptThatIsNotTransactionalIsNeverBuilt(): void
    {
        $renderer = new StubRenderer(new RenderedReceipt('Receipt', '', '<img src="https://track.example/p.gif">', 'Paid'));

        $this->expectException(NotTransactional::class);

        $this->emails($renderer)->payment('R-000042');
    }

    public function testTheDefaultTemplatesGiveATextPartAndNoRemoteContent(): void
    {
        $emails = $this->emails(new EmailTemplatesRenderer(Templates::renderer()), recipient: new Recipient(new Address('ada@example.com', 'Ada'), 'en'), receipts: [Commerce::receipt(), Commerce::creditNote()]);

        foreach ([$emails->payment('R-000042'), $emails->payment('C-000007'), $emails->donation('D-2026-000017')] as $email) {
            self::assertNotSame('', trim((string) $email->getTextBody()));
            self::assertDoesNotMatchRegularExpression('/<img(?![^>]*src="cid:)|url\((?!\s*[\'"]?cid:)/i', (string) $email->getHtmlBody());
            self::assertStringNotContainsString('utm_', (string) $email->getHtmlBody());
        }

        self::assertSame('Credit note C-000007 from The Shop', $emails->payment('C-000007')->getSubject());
    }

    /**
     * @param list<Receipt>|null         $receipts
     * @param list<DonationReceipt>|null $donations
     */
    private function emails(
        ReceiptRenderer $renderer,
        ?Recipient $recipient = new Recipient(new Address('ada@example.com', 'Ada'), 'fr'),
        ?PaymentReceiptPdf $paymentPdf = null,
        ?DonationReceiptPdf $donationPdf = null,
        ?array $receipts = null,
        ?array $donations = null,
    ): ReceiptEmails {
        return new ReceiptEmails(
            new CommercePaymentReceipts(new InMemoryReceipts(...($receipts ?? [Commerce::receipt()]))),
            new InMemoryDonationReceipts(...($donations ?? [Donations::receipt()])),
            new FixedRecipients($recipient),
            $renderer,
            new Address('shop@example.com', 'The Shop'),
            new TransactionalCheck(),
            $paymentPdf,
            $donationPdf,
        );
    }
}
