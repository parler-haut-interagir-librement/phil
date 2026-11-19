<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Repository;

use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;

/**
 * Finds and stores gifts. `Infrastructure\Doctrine\DoctrineGiftRepository` is the Doctrine adapter.
 */
interface GiftRepository
{
    public function find(string $id): ?Gift;

    /**
     * The gift whose current code has this SHA-256, as `Gift::hashOf()` computes it.
     */
    public function findByCodeHash(string $hash): ?Gift;

    public function add(Gift $gift): void;
}
