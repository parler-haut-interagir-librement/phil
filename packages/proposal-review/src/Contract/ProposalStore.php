<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Contract;

use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Proposal;

/**
 * Where the proposals of campaigns are kept, with the reviewers assigned to them and the conflicts of interest declared.
 */
interface ProposalStore
{
    public function get(string $campaign, string $id): ?Proposal;

    /**
     * The proposals of a campaign, in identifier order.
     *
     * @return list<Proposal>
     */
    public function ofCampaign(string $campaign): array;

    /**
     * Keeps a new proposal, or replaces it.
     */
    public function save(Proposal $proposal): void;

    /**
     * Assigns a reviewer to a proposal; nothing happens when they already are.
     */
    public function assign(string $campaign, string $proposal, string $reviewer): void;

    /**
     * The reviewers assigned to a proposal, in identifier order.
     *
     * @return list<string>
     */
    public function reviewersOf(string $campaign, string $proposal): array;

    /**
     * The number of proposals assigned to each reviewer of a campaign who has some.
     *
     * @return array<string, int>
     */
    public function assignmentCounts(string $campaign): array;

    /**
     * Keeps a declared conflict of interest; nothing happens when it is already kept.
     */
    public function declareConflict(ConflictOfInterest $conflict): void;

    /**
     * The conflicts a reviewer declared in a campaign.
     *
     * @return list<ConflictOfInterest>
     */
    public function conflictsOf(string $campaign, string $reviewer): array;
}
