<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application;

use Phil\ReceiptEmail\Application\Port\DonationReceiptPdf;
use Phil\ReceiptEmail\Application\Port\PaymentReceiptPdf;
use Phil\ReceiptEmail\Application\Port\PaymentReceiptSource;
use Phil\ReceiptEmail\Application\Port\ReceiptRenderer;
use Phil\ReceiptEmail\Application\Port\RecipientDirectory;
use Phil\ReceiptEmail\Domain\Exception\PdfUnavailable;
use Phil\ReceiptEmail\Domain\Exception\ReceiptNotFound;
use Phil\ReceiptEmail\Domain\Repository\DonationReceiptRepository;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Builds the email of a receipt from its number: it reads the receipt and its recipient again, renders the message,
 * checks it is transactional, and returns an `Email` with its plain-text and HTML parts, its embedded images, and the
 * PDF attached when the caller asks for it.
 *
 * The email is marked `Auto-Submitted: auto-generated`, so that an auto-responder does not answer it; it has no
 * `List-Unsubscribe`, being no bulk mail. It keeps no state between calls.
 */
final readonly class ReceiptEmails
{
    private Address $from;

    public function __construct(
        private PaymentReceiptSource $payments,
        private DonationReceiptRepository $donations,
        private RecipientDirectory $recipients,
        private ReceiptRenderer $renderer,
        Address|string $from,
        private TransactionalCheck $check = new TransactionalCheck(),
        private ?PaymentReceiptPdf $paymentPdf = null,
        private ?DonationReceiptPdf $donationPdf = null,
    ) {
        $this->from = Address::create($from);
    }

    /**
     * @throws ReceiptNotFound when the receipt or its recipient is not found
     * @throws PdfUnavailable  when the PDF is asked for and no PaymentReceiptPdf is given
     */
    public function payment(string $number, bool $attachPdf = false): Email
    {
        $receipt = $this->payments->find($number) ?? throw ReceiptNotFound::payment($number);
        $recipient = $this->recipients->paymentRecipient($receipt) ?? throw ReceiptNotFound::recipient($number);
        $email = $this->email(ReceiptMessage::payment($receipt, $recipient), $recipient);

        if ($attachPdf) {
            $pdf = $this->paymentPdf ?? throw PdfUnavailable::payment();
            $email->attach($pdf->render($receipt->number, $recipient->locale), $this->fileName('receipt', $receipt->number), 'application/pdf');
        }

        return $email;
    }

    /**
     * @throws ReceiptNotFound when the receipt or its recipient is not found
     * @throws PdfUnavailable  when the PDF is asked for and no DonationReceiptPdf is given
     */
    public function donation(string $serialNumber, bool $attachPdf = false): Email
    {
        $receipt = $this->donations->find($serialNumber) ?? throw ReceiptNotFound::donation($serialNumber);
        $recipient = $this->recipients->donationRecipient($receipt) ?? throw ReceiptNotFound::recipient($serialNumber);
        $email = $this->email(ReceiptMessage::donation($receipt, $recipient), $recipient);

        if ($attachPdf) {
            $pdf = $this->donationPdf ?? throw PdfUnavailable::donation();
            $email->attach($pdf->render($receipt, $recipient->locale), $this->fileName('donation-receipt', $receipt->serialNumber), 'application/pdf');
        }

        return $email;
    }

    private function email(ReceiptMessage $message, Recipient $recipient): Email
    {
        $rendered = $this->renderer->render($message);
        $this->check->check($rendered);

        $email = new Email()
            ->from($this->from)
            ->to($recipient->address)
            ->subject($rendered->subject)
            ->text($rendered->text)
            ->html($rendered->html);
        $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');

        foreach ($rendered->images as $image) {
            $email->embed($image->body, $image->name, $image->contentType);
        }

        return $email;
    }

    /**
     * The attachment's name: the kind and the number, every character a file system might read otherwise replaced.
     */
    private function fileName(string $kind, string $number): string
    {
        return $kind.'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $number).'.pdf';
    }
}
