<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\ProposalReview\Internal\Identifier;
use Phil\ProposalReview\Internal\Utc;

/**
 * A reviewer's scores for a proposal, one per criterion, and the instant they were given, in UTC. A reviewer has one
 * review per proposal; scoring again replaces it. Comments stay in the application.
 */
final readonly class Review
{
    public string $campaign;

    public string $proposal;

    public string $reviewer;

    public DateTimeImmutable $submittedAt;

    /**
     * @param list<Score> $scores
     */
    public function __construct(
        string $campaign,
        string $proposal,
        string $reviewer,
        public array $scores,
        DateTimeInterface $submittedAt,
    ) {
        $this->campaign = Identifier::check($campaign, 'campaign');
        $this->proposal = Identifier::check($proposal, 'proposal');
        $this->reviewer = Identifier::check($reviewer, 'reviewer');
        $this->submittedAt = Utc::of($submittedAt);
    }

    public function scoreFor(string $criterion): ?int
    {
        return array_find($this->scores, static fn (Score $score): bool => $score->criterion === $criterion)?->value;
    }
}
