<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Offer;

/**
 * The unit a billing period counts in.
 */
enum BillingUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
