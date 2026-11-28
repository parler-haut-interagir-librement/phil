<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application\Port;

use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;

/**
 * Prints an official donation receipt as a PDF. `Infrastructure\Pdf\PagedMediaDonationReceiptPdf` prints it with
 * `phil/paged-media`.
 */
interface DonationReceiptPdf
{
    /**
     * @return string the PDF document
     */
    public function render(DonationReceipt $receipt, string $locale): string;
}
