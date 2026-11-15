<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * Says what a channel offers of something, its selection: listed, by direct link only, or hidden; orderable or not;
 * its price shown or not. The catalogue implements it for its products; a sellable no implementation restricts is
 * listed, orderable and priced everywhere.
 *
 * A service implementing it is tagged `phil_commerce.sellable_availability` by autoconfiguration.
 */
interface SellableAvailability
{
    /**
     * `Offer::full()` for a reference this implementation does not know, so that several selections add up; an order
     * without a channel is offered only what every channel offers.
     */
    public function offerThrough(SellableReference $reference, ?string $channel): Offer;
}
