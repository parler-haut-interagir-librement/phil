<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;

/**
 * `OfferRepository` on the Doctrine ORM: an offer is one row of `phil_commerce_subscription_offer`.
 */
final readonly class DoctrineOfferRepository implements OfferRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $code): ?Offer
    {
        return $this->entityManager->find(Offer::class, $code);
    }

    #[Override]
    public function findByCodes(array $codes): array
    {
        $offers = [];

        foreach ($this->entityManager->getRepository(Offer::class)->findBy(['code' => $codes]) as $offer) {
            $offers[$offer->code] = $offer;
        }

        return $offers;
    }

    #[Override]
    public function family(string $family): array
    {
        return $this->entityManager->getRepository(Offer::class)->findBy(['family' => $family], ['rank' => 'ASC', 'code' => 'ASC']);
    }

    #[Override]
    public function add(Offer $offer): void
    {
        $this->entityManager->persist($offer);
    }
}
