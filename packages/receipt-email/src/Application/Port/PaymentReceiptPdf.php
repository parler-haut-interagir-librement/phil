<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application\Port;

/**
 * Prints a payment receipt or a credit note as a PDF, by its number. `Infrastructure\Commerce\CommerceReceiptPdf`
 * prints those of `phil/commerce-bundle` with its own `ReceiptPdf`.
 */
interface PaymentReceiptPdf
{
    /**
     * @return string the PDF document
     */
    public function render(string $number, string $locale): string;
}
