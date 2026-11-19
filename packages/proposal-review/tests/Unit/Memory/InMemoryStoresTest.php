<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Memory;

use DateTimeImmutable;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Decision;
use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Memory\InMemoryCampaignStore;
use Phil\ProposalReview\Memory\InMemoryProposalStore;
use Phil\ProposalReview\Memory\InMemoryReviewStore;
use Phil\ProposalReview\Memory\InMemoryVoteLog;
use Phil\ProposalReview\Pairwise\Vote;
use Phil\ProposalReview\Proposal;
use Phil\ProposalReview\Review;
use Phil\ProposalReview\Tests\Unit\Fixture\Jury;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryCampaignStore::class)]
#[CoversClass(InMemoryProposalStore::class)]
#[CoversClass(InMemoryReviewStore::class)]
#[CoversClass(InMemoryVoteLog::class)]
final class InMemoryStoresTest extends TestCase
{
    public function testCampaignsAreKeptAndReplaced(): void
    {
        $store = new InMemoryCampaignStore();
        $campaign = new Campaign('cfp', Jury::schedule(), [new Criterion('a')]);
        $store->save($campaign);

        self::assertSame($campaign, $store->get('cfp'));
        self::assertNull($store->get('other'));
    }

    public function testProposalsAssignmentsAndConflictsAreKeptPerCampaign(): void
    {
        $store = new InMemoryProposalStore();
        $store->save(new Proposal('cfp', 'b', []));
        $store->save(new Proposal('cfp', 'a', ['u']));
        $store->save(new Proposal('cfp', '10', []));
        $store->save(new Proposal('other', 'c', []));

        $replaced = new Proposal('cfp', 'a', ['v']);
        $store->save($replaced);

        self::assertSame(['10', 'a', 'b'], array_map(static fn (Proposal $proposal): string => $proposal->id, $store->ofCampaign('cfp')));
        self::assertSame($replaced, $store->get('cfp', 'a'));
        self::assertNull($store->get('cfp', 'c'));
        self::assertSame([], $store->ofCampaign('none'));

        $store->assign('cfp', 'a', 'r2');
        $store->assign('cfp', 'a', 'r1');
        $store->assign('cfp', 'a', 'r1');
        $store->assign('cfp', 'b', '7');
        $store->assign('other', 'c', 'r1');

        self::assertSame(['r1', 'r2'], $store->reviewersOf('cfp', 'a'));
        self::assertSame(['7'], $store->reviewersOf('cfp', 'b'));
        self::assertSame([], $store->reviewersOf('cfp', '10'));
        self::assertSame(['r2' => 1, 'r1' => 1, '7' => 1], $store->assignmentCounts('cfp'));
        self::assertSame([], $store->assignmentCounts('none'));

        $conflict = ConflictOfInterest::withProposal('cfp', 'r1', 'a');
        $store->declareConflict($conflict);
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r1', 'a'));
        $store->declareConflict(ConflictOfInterest::withAuthor('cfp', 'r1', 'a'));
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r2', 'a'));
        $store->declareConflict(ConflictOfInterest::withProposal('other', 'r1', 'a'));

        self::assertEquals([$conflict, ConflictOfInterest::withAuthor('cfp', 'r1', 'a')], $store->conflictsOf('cfp', 'r1'));
        self::assertCount(1, $store->conflictsOf('other', 'r1'));
        self::assertSame([], $store->conflictsOf('cfp', 'r3'));
    }

    public function testReviewsAndDecisionsAreKeptAndReplaced(): void
    {
        $store = new InMemoryReviewStore();
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');
        $store->save(new Review('cfp', 'a', 'r2', [], $at));
        $store->save(new Review('cfp', 'a', 'r1', [], $at));

        $again = new Review('cfp', 'a', 'r2', [], $at->modify('+1 hour'));
        $store->save($again);
        $store->save(new Review('cfp', 'b', 'r1', [], $at));

        self::assertSame(['r1', 'r2'], array_map(static fn (Review $review): string => $review->reviewer, $store->ofProposal('cfp', 'a')));
        self::assertSame($again, $store->ofProposal('cfp', 'a')[1]);
        self::assertSame([], $store->ofProposal('cfp', 'z'));

        $store->saveDecision(new DecisionRecord('cfp', 'b', Decision::Accepted, 'chair', $at));
        $store->saveDecision(new DecisionRecord('cfp', 'a', Decision::Accepted, 'chair', $at));
        $store->saveDecision(new DecisionRecord('cfp', 'a', Decision::Rejected, 'chair', $at));

        self::assertSame([['a', Decision::Rejected], ['b', Decision::Accepted]], array_map(static fn (DecisionRecord $record): array => [$record->proposal, $record->decision], $store->decisions('cfp')));
        self::assertSame([], $store->decisions('none'));
    }

    public function testVotesAreNumberedFromOnePerCampaign(): void
    {
        $log = new InMemoryVoteLog();
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');

        self::assertSame(1, $log->append('cfp', 'v', 'a', 'b', $at)->sequence);
        self::assertSame(2, $log->append('cfp', 'v', 'b', 'a', $at)->sequence);
        self::assertSame(1, $log->append('other', 'v', 'a', 'b', $at)->sequence);
        self::assertSame([1, 2], array_map(static fn (Vote $vote): int => $vote->sequence, $log->ofCampaign('cfp')));
        self::assertSame([], $log->ofCampaign('none'));
    }

    public function testAReviewerAssignedToSeveralProposalsIsCountedOnceForEach(): void
    {
        $store = new InMemoryProposalStore();
        $store->assign('cfp', 'a', 'r1');
        $store->assign('cfp', 'b', 'r1');
        $store->assign('cfp', 'c', 'r1');
        $store->assign('cfp', 'c', 'r2');
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r1', 'a'));
        $store->declareConflict(ConflictOfInterest::withProposal('cfp', 'r2', 'a'));

        self::assertSame(['r1' => 3, 'r2' => 1], $store->assignmentCounts('cfp'));
        self::assertEquals([ConflictOfInterest::withProposal('cfp', 'r2', 'a')], $store->conflictsOf('cfp', 'r2'));
    }
}
