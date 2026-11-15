<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Receipt;

/**
 * What a commercial document is. Each kind is numbered in a series of its own, without gaps.
 */
enum DocumentKind: string
{
    /** What the buyer owes for a placed order: its lines, adjustments and taxes as they were agreed to. */
    case Receipt = 'receipt';

    /** The reversal of a receipt, in whole or for some of its lines: every amount it holds counts negatively. */
    case CreditNote = 'credit_note';
}
