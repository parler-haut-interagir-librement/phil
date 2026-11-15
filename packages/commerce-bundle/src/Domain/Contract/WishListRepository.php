<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Contract;

use Phil\CommerceBundle\Domain\Model\WishList;

/**
 * Finds and stores wish lists. `Infrastructure\Doctrine\DoctrineWishListRepository` is the Doctrine adapter.
 */
interface WishListRepository
{
    public function findWishList(string $id): ?WishList;

    /**
     * The list shared under the SHA-256 of a token, or `null`.
     */
    public function findSharedWishList(string $tokenHash): ?WishList;

    /**
     * @return list<WishList> the owner's lists, oldest first, at most `$limit`
     */
    public function wishListsOf(string $owner, int $limit = 100): array;

    public function addWishList(WishList $list): void;

    public function removeWishList(WishList $list): void;
}
