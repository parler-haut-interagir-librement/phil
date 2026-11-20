<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;

final class InMemoryOffers implements OfferRepository
{
    /** @var array<string, Offer> */
    private array $offers = [];

    /** @var list<list<string>> the codes of each `findByCodes()`, in order */
    public array $batches = [];

    public function __construct(Offer ...$offers)
    {
        foreach ($offers as $offer) {
            $this->add($offer);
        }
    }

    #[Override]
    public function find(string $code): ?Offer
    {
        return $this->offers[$code] ?? null;
    }

    #[Override]
    public function findByCodes(array $codes): array
    {
        $this->batches[] = $codes;

        return array_intersect_key($this->offers, array_flip($codes));
    }

    #[Override]
    public function family(string $family): array
    {
        $tiers = array_values(array_filter($this->offers, static fn (Offer $offer): bool => $offer->family === $family));
        usort($tiers, static fn (Offer $left, Offer $right): int => $left->rank <=> $right->rank);

        return $tiers;
    }

    #[Override]
    public function add(Offer $offer): void
    {
        $this->offers[$offer->code] = $offer;
    }

    /**
     * Forgets an offer, as a store whose row was deleted would.
     */
    public function remove(string $code): void
    {
        unset($this->offers[$code]);
    }
}
