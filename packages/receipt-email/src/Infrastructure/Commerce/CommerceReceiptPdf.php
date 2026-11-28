<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\Commerce;

use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Infrastructure\Pdf\ReceiptPdf;
use Phil\ReceiptEmail\Application\Port\PaymentReceiptPdf;
use Phil\ReceiptEmail\Domain\Exception\ReceiptNotFound;

/**
 * Prints a receipt or a credit note of `phil/commerce-bundle` with its own `ReceiptPdf`: the printer of the
 * recipient's locale, else of its language, else the default one.
 */
final readonly class CommerceReceiptPdf implements PaymentReceiptPdf
{
    /**
     * @param array<string, ReceiptPdf> $printers locale or language → its printer, `fr` → one with French labels
     */
    public function __construct(
        private ReceiptRepository $receipts,
        private ReceiptPdf $default = new ReceiptPdf(),
        private array $printers = [],
    ) {
    }

    public function render(string $number, string $locale): string
    {
        $receipt = $this->receipts->find($number) ?? throw ReceiptNotFound::payment($number);
        $printer = $this->printers[$locale] ?? $this->printers[explode('_', $locale, 2)[0]] ?? $this->default;

        return $printer->render($receipt);
    }
}
