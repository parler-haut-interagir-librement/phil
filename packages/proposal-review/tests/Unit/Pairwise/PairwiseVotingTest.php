<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Pairwise;

use DateTimeImmutable;
use Override;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Contract\VoteLog;
use Phil\ProposalReview\Event\PairwiseVoteCast;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Exception\NotFound;
use Phil\ProposalReview\Exception\Refused;
use Phil\ProposalReview\Memory\InMemoryVoteLog;
use Phil\ProposalReview\Pairwise\Matchup;
use Phil\ProposalReview\Pairwise\PairwiseScore;
use Phil\ProposalReview\Pairwise\PairwiseVoting;
use Phil\ProposalReview\Pairwise\Vote;
use Phil\ProposalReview\Tests\Unit\Fixture\Jury;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SortDirection;

#[CoversClass(PairwiseVoting::class)]
#[CoversClass(Matchup::class)]
#[CoversClass(PairwiseVoteCast::class)]
final class PairwiseVotingTest extends TestCase
{
    public function testVotesAreCastDuringReviewAndLogged(): void
    {
        [$jury, $voting, $log] = $this->voting(['e1' => ['u-1'], 'e2' => ['u-2'], 'e3' => ['u-3']]);

        $this->assertRefused(static fn () => $voting->vote('v-1', 'cfp', 'e1', 'e2'), 'Pairwise votes are cast during the review phase.');

        $jury->at('2026-11-12 10:00:00 UTC');
        $vote = $voting->vote('v-1', 'cfp', 'e1', 'e2');
        self::assertSame([1, 'v-1', 'e1', 'e2'], [$vote->sequence, $vote->voter, $vote->winner, $vote->loser]);
        self::assertSame('2026-11-12T10:00:00+00:00', $vote->castAt->format(\DATE_ATOM));
        self::assertSame(2, $voting->vote('v-2', 'cfp', 'e2', 'e3')->sequence);
        self::assertCount(2, $log->ofCampaign('cfp'));
        self::assertEquals([new PairwiseVoteCast('cfp', 1), new PairwiseVoteCast('cfp', 2)], $jury->events->dispatched);

        $this->assertRefused(static fn () => $voting->vote('u-1', 'cfp', 'e1', 'e2'), 'A voter in conflict with a proposal does not judge it.');
        $this->assertRefused(static fn () => $voting->vote('u-2', 'cfp', 'e1', 'e2'), 'A voter in conflict with a proposal does not judge it.');

        try {
            $voting->vote('v-1', 'cfp', 'e1', 'e1');
            self::fail('A vote compares two proposals.');
        } catch (InvalidInput $invalidInput) {
            self::assertSame('A vote compares two different proposals.', $invalidInput->getMessage());
        }

        $this->expectException(NotFound::class);
        $voting->vote('v-1', 'cfp', 'e1', 'nope');
    }

    public function testVoterIdentifiersAreChecked(): void
    {
        [$jury, $voting] = $this->voting(['e1' => [], 'e2' => []]);
        $jury->at('2026-11-12 10:00:00 UTC');

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIs('Voter identifiers are stable opaque identifiers, never e-mail addresses.');
        $voting->vote('v@x.example', 'cfp', 'e1', 'e2');
    }

    public function testPairsBalanceExposureAndNeverRepeatForAVoter(): void
    {
        [$jury, $voting] = $this->voting(['e1' => [], 'e2' => [], 'e3' => [], 'e4' => []]);
        $jury->at('2026-11-12 10:00:00 UTC');
        $seen = [];

        for ($vote = 0; $vote < 6; ++$vote) {
            $pair = $voting->nextPair('v-1', 'cfp');
            self::assertNotNull($pair);
            self::assertNotSame($pair->left, $pair->right);
            $key = min($pair->left, $pair->right).'|'.max($pair->left, $pair->right);
            self::assertArrayNotHasKey($key, $seen);
            $seen[$key] = true;
            $voting->vote('v-1', 'cfp', $pair->left, $pair->right);
        }

        self::assertNull($voting->nextPair('v-1', 'cfp'));
        self::assertCount(6, $seen);

        $exposure = array_count_values(array_merge(...array_map(static fn (string $key): array => explode('|', $key), array_keys($seen))));
        self::assertSame([3], array_values(array_unique($exposure)));
    }

    public function testTheLeastExposedProposalsComeFirst(): void
    {
        [$jury, $voting] = $this->voting(['e1' => [], 'e2' => [], 'e3' => [], 'e4' => []]);
        $jury->at('2026-11-12 10:00:00 UTC');
        $voting->vote('v-2', 'cfp', 'e1', 'e2');
        $voting->vote('v-3', 'cfp', 'e2', 'e1');

        $pair = $voting->nextPair('v-1', 'cfp');
        self::assertSame(['e3', 'e4'], [min($pair?->left, $pair?->right), max($pair?->left, $pair?->right)]);
        self::assertEquals($pair, $voting->nextPair('v-1', 'cfp'));
    }

    public function testConflictsAreLeftOutOfThePairs(): void
    {
        [$jury, $voting] = $this->voting(['e1' => ['u-1'], 'e2' => [], 'e3' => []]);
        $jury->board->declareConflict(ConflictOfInterest::withProposal('cfp', 'u-1', 'e2'));
        $jury->at('2026-11-12 10:00:00 UTC');

        self::assertNull($voting->nextPair('u-1', 'cfp'));
        self::assertNotNull($voting->nextPair('v-1', 'cfp'));
    }

    public function testScoresComeFromTheWholeLog(): void
    {
        [$jury, $voting] = $this->voting(['e1' => [], 'e2' => [], 'e3' => []]);
        $jury->at('2026-11-12 10:00:00 UTC');

        foreach ([['e1', 'e2'], ['e1', 'e3'], ['e2', 'e3'], ['e1', 'e2'], ['e3', 'e2']] as $number => [$winner, $loser]) {
            $voting->vote('v-'.$number, 'cfp', $winner, $loser);
        }

        $scores = $voting->scores('cfp');
        self::assertSame(['e1', 'e3', 'e2'], array_map(static fn (PairwiseScore $score): string => $score->proposal, $scores));
        self::assertSame(['e2', 'e3', 'e1'], array_map(static fn (PairwiseScore $score): string => $score->proposal, $voting->scores('cfp', SortDirection::Ascending)));
        self::assertEquals($scores, $voting->scores('cfp'));

        [, $empty] = $this->voting(['b' => [], 'a' => []]);
        self::assertSame(['a', 'b'], array_map(static fn (PairwiseScore $score): string => $score->proposal, $empty->scores('cfp')));
        self::assertSame(['a', 'b'], array_map(static fn (PairwiseScore $score): string => $score->proposal, $empty->scores('cfp', SortDirection::Ascending)));
    }

    public function testAnUnknownCampaignIsNotFound(): void
    {
        [, $voting] = $this->voting([]);

        foreach ([static fn () => $voting->nextPair('v', 'nope'), static fn () => $voting->scores('nope')] as $call) {
            try {
                $call();
                self::fail('The campaign is unknown.');
            } catch (NotFound $notFound) {
                self::assertSame('There is no campaign "nope".', $notFound->getMessage());
            }
        }
    }

    public function testVotingWithoutDispatcher(): void
    {
        $jury = new Jury();
        $jury->board->submit('cfp', 'a', []);
        $jury->board->submit('cfp', 'b', []);

        $voting = new PairwiseVoting($jury->board, $jury->proposals, new InMemoryVoteLog(), $jury->clock);
        $jury->at('2026-11-12 10:00:00 UTC');

        self::assertSame(1, $voting->vote('v', 'cfp', 'a', 'b')->sequence);
        self::assertSame([], $jury->events->dispatched);
    }

    /**
     * @param array<string, list<string>> $proposals
     *
     * @return array{Jury, PairwiseVoting, InMemoryVoteLog}
     */
    private function voting(array $proposals): array
    {
        $jury = new Jury();

        foreach ($proposals as $id => $authors) {
            $jury->board->submit('cfp', $id, $authors);
        }

        $log = new InMemoryVoteLog();

        return [$jury, new PairwiseVoting($jury->board, $jury->proposals, $log, $jury->clock, events: $jury->events), $log];
    }

    private function assertRefused(callable $call, string $message): void
    {
        try {
            $call();
            self::fail($message);
        } catch (Refused $refused) {
            self::assertSame($message, $refused->getMessage());
        }
    }

    public function testThePairIsDrawnFromTheExposureTheCampaignTheVoterAndTheirVoteCount(): void
    {
        [$jury, $voting, $log] = $this->voting(['e1' => [], 'e2' => [], 'e3' => [], 'e4' => [], 'e5' => []]);
        $jury->at('2026-11-12 10:00:00 UTC');
        $voting->vote('v-a', 'cfp', 'e1', 'e2');
        $voting->vote('v-b', 'cfp', 'e3', 'e1');
        $voting->vote('v-b', 'cfp', 'e3', 'e2');

        for ($voter = 0; $voter < 12; ++$voter) {
            $pair = $voting->nextPair('v-'.$voter, 'cfp');
            $expected = $this->expectedPair($log, 'v-'.$voter, ['e1', 'e2', 'e3', 'e4', 'e5']);

            self::assertSame($expected, [$pair?->left, $pair?->right], 'Voter '.$voter);
        }

        for ($cast = 0; $cast < 8; ++$cast) {
            $pair = $voting->nextPair('v-z', 'cfp');
            self::assertNotNull($pair);
            self::assertSame($this->expectedPair($log, 'v-z', ['e1', 'e2', 'e3', 'e4', 'e5']), [$pair->left, $pair->right], 'Vote '.$cast);
            $voting->vote('v-z', 'cfp', $pair->left, $pair->right);
        }
    }

    public function testTheLogIsTheWorkOfTheLogNotOfTheVotingServiceChecks(): void
    {
        $jury = new Jury();
        $jury->board->submit('cfp', 'a', []);
        $jury->board->submit('cfp', 'b', []);
        $jury->at('2026-11-12 10:00:00 UTC');

        $log = new class implements VoteLog {
            public int $appended = 0;

            #[Override]
            public function append(string $campaign, string $voter, string $winner, string $loser, DateTimeImmutable $castAt): Vote
            {
                ++$this->appended;

                return new Vote($campaign, $this->appended, 'recorded', 'a', 'b', $castAt);
            }

            #[Override]
            public function ofCampaign(string $campaign): array
            {
                return [];
            }
        };
        $voting = new PairwiseVoting($jury->board, $jury->proposals, $log, $jury->clock);

        foreach ([['v@x.example', 'a', 'b', 'Voter identifiers are stable opaque identifiers, never e-mail addresses.'], ['v', 'a', 'a', 'A vote compares two different proposals.']] as [$voter, $winner, $loser, $message]) {
            try {
                $voting->vote($voter, 'cfp', $winner, $loser);
                self::fail($message);
            } catch (InvalidInput $invalidInput) {
                self::assertSame($message, $invalidInput->getMessage());
            }
        }

        self::assertSame(0, $log->appended);
        self::assertSame(1, $voting->vote('v', 'cfp', 'a', 'b')->sequence);
    }

    public function testScoresRunBothWaysWithTiesInIdentifierOrder(): void
    {
        [$jury, $voting] = $this->voting(['e1' => [], 'e2' => [], 'e3' => []]);
        $jury->at('2026-11-12 10:00:00 UTC');
        $voting->vote('v-1', 'cfp', 'e3', 'e1');

        $order = static fn (SortDirection $direction): array => array_map(static fn (PairwiseScore $score): string => $score->proposal, $voting->scores('cfp', $direction));

        self::assertSame(['e3', 'e2', 'e1'], $order(SortDirection::Descending));
        self::assertSame(['e1', 'e2', 'e3'], $order(SortDirection::Ascending));
    }

    /**
     * What the draw gives a voter, worked out from the log alone: the proposals seen least first, between equals the
     * hash of the campaign, the voter, the votes they cast and the proposal; the first one meets the first it has not
     * been compared with.
     *
     * @param list<string> $ids
     *
     * @return array{string, string}
     */
    private function expectedPair(InMemoryVoteLog $log, string $voter, array $ids): array
    {
        $exposure = array_fill_keys($ids, 0);
        $judged = [];
        $cast = 0;

        foreach ($log->ofCampaign('cfp') as $vote) {
            ++$exposure[$vote->winner];
            ++$exposure[$vote->loser];

            if ($vote->voter === $voter) {
                $judged[] = [$vote->winner, $vote->loser];
                $judged[] = [$vote->loser, $vote->winner];
                ++$cast;
            }
        }

        $rows = array_map(static fn (string $id): array => [$exposure[$id], hash('sha256', "cfp\0".$voter."\0".$cast."\0".$id), $id], $ids);
        usort($rows, static fn (array $a, array $b): int => $a <=> $b);
        $order = array_column($rows, 2);

        foreach ($order as $first) {
            foreach ($order as $second) {
                if ($first !== $second && !\in_array([$first, $second], $judged, true)) {
                    return [$first, $second];
                }
            }
        }

        self::fail('Every pair is judged.');
    }
}
