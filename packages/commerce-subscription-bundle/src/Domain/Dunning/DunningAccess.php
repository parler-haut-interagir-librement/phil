<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Dunning;

/**
 * What a subscriber keeps of their tier while its renewal is past due, from one stage of the offer's dunning on.
 */
enum DunningAccess: string
{
    /** Every feature of the tier, as when paid. */
    case Full = 'full';

    /** The features the stage names, a part of the tier's. */
    case Reduced = 'reduced';

    /** No feature: access is cut until the renewal is paid. */
    case None = 'none';
}
