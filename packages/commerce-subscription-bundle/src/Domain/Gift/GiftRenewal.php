<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Gift;

/**
 * What happens to a gift subscription once the periods the buyer paid are over.
 */
enum GiftRenewal: string
{
    /** It renews, each period ordered for the buyer to pay, until cancelled. */
    case BuyerPays = 'buyer_pays';

    /** It ends at the end of the periods paid, unless the beneficiary resumes it at their own charge. */
    case EndsAtTerm = 'ends_at_term';
}
