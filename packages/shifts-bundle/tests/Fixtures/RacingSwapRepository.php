<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Fixtures;

use Override;
use Phil\Shifts\Swap\SwapOffer;
use Phil\ShiftsBundle\Domain\Model\StoredSwap;
use Phil\ShiftsBundle\Domain\Repository\SwapRepository;

/**
 * A repository of offers whose first writes lose to another request.
 */
final class RacingSwapRepository implements SwapRepository
{
    public ?int $limit = null;

    public function __construct(
        private readonly SwapRepository $inner,
        private int $losses,
    ) {
    }

    #[Override]
    public function add(SwapOffer $offer): void
    {
        $this->inner->add($offer);
    }

    #[Override]
    public function update(SwapOffer $offer, int $revision): bool
    {
        if ($this->losses > 0) {
            --$this->losses;

            return false;
        }

        return $this->inner->update($offer, $revision);
    }

    #[Override]
    public function find(string $id): ?StoredSwap
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function active(int $limit = 200): array
    {
        $this->limit = $limit;

        return $this->inner->active($limit);
    }

    #[Override]
    public function ofVolunteer(string $volunteer): array
    {
        return $this->inner->ofVolunteer($volunteer);
    }

    #[Override]
    public function eraseVolunteer(string $volunteer): void
    {
        $this->inner->eraseVolunteer($volunteer);
    }
}
