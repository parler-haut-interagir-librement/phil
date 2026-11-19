<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Fixture;

use DateTimeImmutable;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Memory\InMemoryCampaignStore;
use Phil\ProposalReview\Memory\InMemoryProposalStore;
use Phil\ProposalReview\Memory\InMemoryReviewStore;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\ReviewBoard;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Schedule;
use Symfony\Component\Clock\MockClock;

/**
 * A campaign of November 2026 on stores in memory and a mock clock: submission from the 1st, review from the 10th,
 * author response from the 20th, deliberation from the 23rd to the 30th.
 */
final readonly class Jury
{
    public MockClock $clock;

    public InMemoryCampaignStore $campaigns;

    public InMemoryProposalStore $proposals;

    public InMemoryReviewStore $reviews;

    public RecordingEvents $events;

    public ReviewBoard $board;

    public function __construct(ReviewPolicy $policy = new ReviewPolicy(minReviews: 2), string $now = '2026-11-05 12:00:00 UTC')
    {
        $this->clock = new MockClock($now);
        $this->campaigns = new InMemoryCampaignStore();
        $this->proposals = new InMemoryProposalStore();
        $this->reviews = new InMemoryReviewStore();
        $this->events = new RecordingEvents();
        $this->campaigns->save(new Campaign('cfp', self::schedule(), [new Criterion('content', 2, 1, 5), new Criterion('clarity', 1, 0, 10)], $policy));
        $this->board = new ReviewBoard($this->campaigns, $this->proposals, $this->reviews, $this->clock, $this->events);
    }

    public static function schedule(): Schedule
    {
        return new Schedule([
            new PhaseWindow(Phase::Submission, new DateTimeImmutable('2026-11-01 00:00:00 UTC'), new DateTimeImmutable('2026-11-10 00:00:00 UTC')),
            new PhaseWindow(Phase::Review, new DateTimeImmutable('2026-11-10 00:00:00 UTC'), new DateTimeImmutable('2026-11-20 00:00:00 UTC')),
            new PhaseWindow(Phase::AuthorResponse, new DateTimeImmutable('2026-11-20 00:00:00 UTC'), new DateTimeImmutable('2026-11-23 00:00:00 UTC')),
            new PhaseWindow(Phase::Deliberation, new DateTimeImmutable('2026-11-23 00:00:00 UTC'), new DateTimeImmutable('2026-11-30 00:00:00 UTC')),
        ]);
    }

    public function at(string $moment): self
    {
        $this->clock->modify($moment);

        return $this;
    }
}
