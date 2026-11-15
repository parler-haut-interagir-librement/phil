<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Contract\WishListRepository;
use Phil\CommerceBundle\Domain\Model\WishList;

/**
 * `WishListRepository` on the Doctrine ORM: a list is one row of `phil_commerce_wish_list`, its things in a JSON
 * column, found by its id, by the hash of its link, or by its owner.
 */
final readonly class DoctrineWishListRepository implements WishListRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findWishList(string $id): ?WishList
    {
        return $this->entityManager->find(WishList::class, $id);
    }

    #[Override]
    public function findSharedWishList(string $tokenHash): ?WishList
    {
        return $this->entityManager->getRepository(WishList::class)->findOneBy(['shareHash' => $tokenHash]);
    }

    #[Override]
    public function wishListsOf(string $owner, int $limit = 100): array
    {
        return $this->entityManager->getRepository(WishList::class)->findBy(['owner' => $owner], ['createdAt' => 'ASC', 'id' => 'ASC'], max(1, $limit));
    }

    #[Override]
    public function addWishList(WishList $list): void
    {
        $this->entityManager->persist($list);
    }

    #[Override]
    public function removeWishList(WishList $list): void
    {
        $this->entityManager->remove($list);
    }
}
