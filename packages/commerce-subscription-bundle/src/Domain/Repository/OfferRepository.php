<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Repository;

use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;

/**
 * Finds and stores offers. `Infrastructure\Doctrine\DoctrineOfferRepository` is the Doctrine adapter.
 */
interface OfferRepository
{
    public function find(string $code): ?Offer;

    /**
     * The offers of these codes in one read; a code without an offer is absent.
     *
     * @param list<string> $codes
     *
     * @return array<string, Offer> keyed by code
     */
    public function findByCodes(array $codes): array;

    /**
     * @return list<Offer> the tiers of a family, by rank
     */
    public function family(string $family): array;

    public function add(Offer $offer): void;
}
