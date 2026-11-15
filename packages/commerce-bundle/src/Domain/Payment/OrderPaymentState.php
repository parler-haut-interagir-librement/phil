<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Payment;

/**
 * Where an order stands with its payments, from what they captured and what went back.
 */
enum OrderPaymentState: string
{
    /** Nothing captured. */
    case Unpaid = 'unpaid';

    /** Captured, less than the total, nothing gone back. */
    case PartiallyPaid = 'partially_paid';

    /** Captured the total, nothing gone back. */
    case Paid = 'paid';

    /** Something went back, something stays. */
    case PartiallyRefunded = 'partially_refunded';

    /** Everything captured went back. */
    case Refunded = 'refunded';
}
