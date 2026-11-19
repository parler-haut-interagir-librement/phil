<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Memory;

use Override;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Contract\ProposalStore;
use Phil\ProposalReview\Proposal;

/**
 * Proposals, assignments and conflicts in memory, for tests and single runs; they last as long as the object.
 */
final class InMemoryProposalStore implements ProposalStore
{
    /** @var array<string, array<string, Proposal>> */
    private array $proposals = [];

    /** @var array<string, array<string, list<string>>> reviewers by proposal by campaign */
    private array $assignments = [];

    /** @var list<ConflictOfInterest> */
    private array $conflicts = [];

    #[Override]
    public function get(string $campaign, string $id): ?Proposal
    {
        return $this->proposals[$campaign][$id] ?? null;
    }

    #[Override]
    public function ofCampaign(string $campaign): array
    {
        $proposals = $this->proposals[$campaign] ?? [];
        usort($proposals, static fn (Proposal $a, Proposal $b): int => strcmp($a->id, $b->id));

        return $proposals;
    }

    #[Override]
    public function save(Proposal $proposal): void
    {
        $this->proposals[$proposal->campaign][$proposal->id] = $proposal;
    }

    #[Override]
    public function assign(string $campaign, string $proposal, string $reviewer): void
    {
        if (!\in_array($reviewer, $this->assignments[$campaign][$proposal] ?? [], true)) {
            $this->assignments[$campaign][$proposal][] = $reviewer;
        }
    }

    #[Override]
    public function reviewersOf(string $campaign, string $proposal): array
    {
        $reviewers = $this->assignments[$campaign][$proposal] ?? [];
        sort($reviewers, \SORT_STRING);

        return $reviewers;
    }

    #[Override]
    public function assignmentCounts(string $campaign): array
    {
        $counts = [];

        foreach ($this->assignments[$campaign] ?? [] as $reviewers) {
            foreach ($reviewers as $reviewer) {
                $counts[$reviewer] = ($counts[$reviewer] ?? 0) + 1;
            }
        }

        return $counts;
    }

    #[Override]
    public function declareConflict(ConflictOfInterest $conflict): void
    {
        foreach ($this->conflicts as $kept) {
            if ([$kept->campaign, $kept->reviewer, $kept->proposal, $kept->author] === [$conflict->campaign, $conflict->reviewer, $conflict->proposal, $conflict->author]) {
                return;
            }
        }

        $this->conflicts[] = $conflict;
    }

    #[Override]
    public function conflictsOf(string $campaign, string $reviewer): array
    {
        return array_values(array_filter(
            $this->conflicts,
            static fn (ConflictOfInterest $conflict): bool => $conflict->campaign === $campaign && $conflict->reviewer === $reviewer,
        ));
    }
}
