<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SeatRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;

/**
 * The seats of an organization's subscription to an offer that counts them: which members use its features.
 *
 * An offer without seats opens its features to every member of the organization, and takes no seat. Who may assign or
 * release a seat is the application's to decide. Nothing is committed here.
 */
final readonly class Seats
{
    public function __construct(
        private OfferRepository $offers,
        private SeatRepository $seats,
        private ?FeatureAccess $access = null,
    ) {
    }

    /**
     * Gives the member a seat, or returns the one they hold; refused when every seat is taken.
     *
     * @param string $member the member's stable identifier, never an email address or a username
     */
    public function assign(Subscription $subscription, string $member, DateTimeImmutable $at): Seat
    {
        $identifier = StableIdentifier::of($member, 'member');
        $count = $this->offer($subscription)->seats ?? throw SubscriptionRefused::withoutSeats($subscription->offerCode());
        $held = $this->seats->find($subscription->id, $identifier->value);

        if ($held instanceof Seat) {
            return $held;
        }

        if ($this->seats->count($subscription->id) >= $count) {
            throw SubscriptionRefused::seatsTaken($subscription->id, $count);
        }

        $seat = Seat::assign($subscription, $identifier, $at);
        $this->seats->add($seat);
        $this->access?->forget($identifier->value);

        return $seat;
    }

    /**
     * Frees the member's seat, if they hold one; a member waiting for a seat may take its place.
     */
    public function release(Subscription $subscription, string $member): void
    {
        $seat = $this->seats->find($subscription->id, $member);

        if (!$seat instanceof Seat) {
            return;
        }

        $this->seats->remove($seat);
        $this->access?->forget($subscription->subscriber);
    }

    /**
     * @return list<Seat> earliest assigned first
     */
    public function holders(Subscription $subscription): array
    {
        return $this->seats->holders($subscription->id);
    }

    private function offer(Subscription $subscription): Offer
    {
        return $this->offers->find($subscription->offerCode()) ?? throw SubscriptionRefused::unknownOffer($subscription->offerCode());
    }
}
