<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * What a channel offers of a sellable, every selection asked: the strictest answer, `Offer::full()` when none
 * restricts it. A page reads it to list a sellable, show its page, its price and the button that buys it;
 * `OrderModifier` reads the same selections to refuse a line.
 *
 * The asking stops at the first hidden offer, which no other answer can loosen.
 */
final readonly class SellableOffers
{
    /**
     * @param iterable<SellableAvailability> $availabilities the channels' selections
     */
    public function __construct(
        private iterable $availabilities = [],
    ) {
    }

    public function of(SellableReference $reference, ?string $channel): Offer
    {
        $reference = $reference->unconfigured();
        $offer = Offer::full();

        foreach ($this->availabilities as $availability) {
            $offer = $offer->stricter($availability->offerThrough($reference, $channel));

            if (Visibility::Hidden === $offer->visibility) {
                break;
            }
        }

        return $offer;
    }
}
