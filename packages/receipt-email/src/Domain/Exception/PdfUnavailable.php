<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Exception;

use LogicException;

/**
 * A PDF asked for while no PDF renderer is given: the application configures one before asking.
 */
final class PdfUnavailable extends LogicException implements ReceiptEmailException
{
    public static function payment(): self
    {
        return new self('The PDF of a payment receipt is asked for, and no PaymentReceiptPdf is given.');
    }

    public static function donation(): self
    {
        return new self('The PDF of a donation receipt is asked for, and no DonationReceiptPdf is given.');
    }
}
