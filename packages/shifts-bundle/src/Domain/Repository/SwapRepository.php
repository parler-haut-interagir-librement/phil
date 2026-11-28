<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Repository;

use Phil\Shifts\Swap\SwapOffer;
use Phil\ShiftsBundle\Domain\Model\StoredSwap;

/**
 * The offers of the swap board. A write is conditional on the revision read.
 */
interface SwapRepository
{
    public function add(SwapOffer $offer): void;

    /**
     * Writes the offer over the revision read; false when another write came first.
     */
    public function update(SwapOffer $offer, int $revision): bool;

    /**
     * @phpstan-impure
     */
    public function find(string $id): ?StoredSwap;

    /**
     * The offers open or waiting for a coordinator, oldest first, 500 at most.
     *
     * @return list<SwapOffer>
     *
     * @phpstan-impure
     */
    public function active(int $limit = 200): array;

    /**
     * The offers the volunteer made or took.
     *
     * @return list<SwapOffer>
     *
     * @phpstan-impure
     */
    public function ofVolunteer(string $volunteer): array;

    /**
     * Deletes the offers the volunteer made or took.
     */
    public function eraseVolunteer(string $volunteer): void;
}
