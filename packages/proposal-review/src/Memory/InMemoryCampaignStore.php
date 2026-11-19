<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Memory;

use Override;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Contract\CampaignStore;

/**
 * Campaigns in memory, for tests and single runs; they last as long as the object.
 */
final class InMemoryCampaignStore implements CampaignStore
{
    /** @var array<string, Campaign> */
    private array $campaigns = [];

    #[Override]
    public function get(string $id): ?Campaign
    {
        return $this->campaigns[$id] ?? null;
    }

    #[Override]
    public function save(Campaign $campaign): void
    {
        $this->campaigns[$campaign->id] = $campaign;
    }
}
