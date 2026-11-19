<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Contract;

use Phil\ProposalReview\Campaign;

/**
 * Where campaigns are kept.
 */
interface CampaignStore
{
    public function get(string $id): ?Campaign;

    /**
     * Keeps a new campaign, or the new state of one.
     */
    public function save(Campaign $campaign): void;
}
