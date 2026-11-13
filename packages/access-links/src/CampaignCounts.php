<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

/**
 * The counters of a campaign: how many recipients were invited, how many answered, how many invitations were revoked.
 */
final readonly class CampaignCounts
{
    public function __construct(
        public int $invited,
        public int $responded,
        public int $revoked,
    ) {
    }

    /**
     * The invitations neither answered nor revoked: the recipients a reminder may still reach.
     */
    public function pending(): int
    {
        return $this->invited - $this->responded - $this->revoked;
    }
}
