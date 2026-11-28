<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Donation;

/**
 * What a donor gave: money, or property counted at its fair market value when it was given.
 */
enum GiftKind: string
{
    case Cash = 'cash';

    /** Property: the receipt describes it, and names its appraiser when it was appraised. */
    case NonCash = 'non_cash';
}
