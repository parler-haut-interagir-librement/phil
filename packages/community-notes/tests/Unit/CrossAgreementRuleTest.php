<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\CommunityNotes\Bridge;
use Phil\CommunityNotes\CrossAgreementRule;
use Phil\CommunityNotes\Decision;
use Phil\CommunityNotes\DecisionReason;
use Phil\CommunityNotes\Memory\InMemoryRatingRepository;
use Phil\CommunityNotes\NoteStatus;
use Phil\CommunityNotes\PairHistory;
use Phil\CommunityNotes\Rating;
use Phil\CommunityNotes\Tests\Unit\Fixture\FixedHistory;
use Phil\CommunityNotes\Verdict;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CrossAgreementRule::class)]
#[CoversClass(Decision::class)]
#[CoversClass(Bridge::class)]
final class CrossAgreementRuleTest extends TestCase
{
    private InMemoryRatingRepository $ratings;

    protected function setUp(): void
    {
        $this->ratings = new InMemoryRatingRepository();
        // Two camps: ann and bob against cid and dan on three earlier notes; eve agrees with everyone.
        foreach (['old-1', 'old-2', 'old-3'] as $i => $note) {
            $this->rate($note, 'ann', Verdict::Helpful, $i);
            $this->rate($note, 'bob', Verdict::Helpful, $i);
            $this->rate($note, 'cid', Verdict::NotHelpful, $i);
            $this->rate($note, 'dan', Verdict::NotHelpful, $i);
        }
    }

    public function testRatersWhoUsuallyDisagreePublishANote(): void
    {
        foreach (['ann', 'bob', 'cid', 'dan', 'eve'] as $i => $rater) {
            $this->rate('n', $rater, Verdict::Helpful, 10 + $i);
        }

        $decision = new CrossAgreementRule()->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([NoteStatus::Helpful, DecisionReason::CrossAgreement, Verdict::Helpful, 5, 0, 5], [$decision->status, $decision->reason, $decision->verdict, $decision->helpful, $decision->notHelpful, $decision->ratings()]);
        self::assertSame([
            ['first' => 'user:ann', 'second' => 'user:cid', 'shared' => 3, 'agreed' => 0],
            ['first' => 'user:bob', 'second' => 'user:dan', 'shared' => 3, 'agreed' => 0],
        ], array_map(static fn (Bridge $bridge): array => $bridge->toArray(), $decision->bridges));
        self::assertSame([
            'status' => 'helpful',
            'reason' => 'cross_agreement',
            'helpful' => 5,
            'not_helpful' => 0,
            'verdict' => 'helpful',
            'bridges' => array_map(static fn (Bridge $bridge): array => $bridge->toArray(), $decision->bridges),
            'rule' => ['min_ratings' => 5, 'share' => '2/3', 'pairs' => 2, 'min_shared' => 3, 'divergence' => '1/2', 'max_raters' => 50],
        ], $decision->toArray());
        self::assertSame([
            'status' => 'helpful',
            'reason' => 'cross_agreement',
            'helpful' => 5,
            'not_helpful' => 0,
            'verdict' => 'helpful',
            'bridges' => [['shared' => 3, 'agreed' => 0], ['shared' => 3, 'agreed' => 0]],
            'rule' => ['min_ratings' => 5, 'share' => '2/3', 'pairs' => 2, 'min_shared' => 3, 'divergence' => '1/2', 'max_raters' => 50],
        ], $decision->toPublicArray());
    }

    public function testOneCampAloneDoesNotPublish(): void
    {
        foreach (['ann', 'bob', 'eve', 'fay', 'gus'] as $i => $rater) {
            $this->rate('n', $rater, Verdict::Helpful, 10 + $i);
        }

        $decision = new CrossAgreementRule()->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([NoteStatus::NeedsMoreRatings, DecisionReason::NoCrossAgreement, Verdict::Helpful, []], [$decision->status, $decision->reason, $decision->verdict, $decision->bridges]);
    }

    public function testRatersWhoUsuallyDisagreeDecideANoteNotHelpful(): void
    {
        foreach (['ann', 'bob', 'cid', 'dan'] as $i => $rater) {
            $this->rate('n', $rater, Verdict::NotHelpful, 10 + $i);
        }

        $this->rate('n', 'eve', Verdict::Helpful, 20);
        $decision = new CrossAgreementRule()->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([NoteStatus::NotHelpful, DecisionReason::CrossAgreement, Verdict::NotHelpful, 1, 4], [$decision->status, $decision->reason, $decision->verdict, $decision->helpful, $decision->notHelpful]);
    }

    public function testTooFewRatingsOrNoClearMajority(): void
    {
        $this->rate('n', 'ann', Verdict::Helpful, 10);
        $this->rate('n', 'cid', Verdict::Helpful, 11);
        $rule = new CrossAgreementRule();
        $few = $rule->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([NoteStatus::NeedsMoreRatings, DecisionReason::TooFewRatings, null, 2], [$few->status, $few->reason, $few->verdict, $few->ratings()]);

        $this->rate('n', 'bob', Verdict::NotHelpful, 12);
        $this->rate('n', 'dan', Verdict::NotHelpful, 13);
        $this->rate('n', 'eve', Verdict::Helpful, 14);
        $split = $rule->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([NoteStatus::NeedsMoreRatings, DecisionReason::NoClearMajority, null, 3, 2], [$split->status, $split->reason, $split->verdict, $split->helpful, $split->notHelpful]);

        // Exactly the share: two thirds of six.
        $this->rate('n', 'fay', Verdict::Helpful, 15);
        self::assertSame(Verdict::Helpful, $rule->decide('n', $this->ratings->ofNote('n'), $this->ratings)->verdict);
    }

    public function testEachRaterIsInOnePairAtMostAndTheEarliestComeFirst(): void
    {
        // ann against cid, dan and bob against cid: ann pairs with cid, then bob with dan.
        $this->rate('n', 'dan', Verdict::Helpful, 13);
        $this->rate('n', 'ann', Verdict::Helpful, 10);
        $this->rate('n', 'bob', Verdict::Helpful, 12);
        $this->rate('n', 'cid', Verdict::Helpful, 11);
        $decision = new CrossAgreementRule(minRatings: 4, pairs: 2)->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([['user:ann', 'user:cid'], ['user:bob', 'user:dan']], array_map(static fn (Bridge $bridge): array => [$bridge->first->toString(), $bridge->second->toString()], $decision->bridges));

        $one = new CrossAgreementRule(minRatings: 4, pairs: 1)->decide('n', $this->ratings->ofNote('n'), $this->ratings);
        self::assertCount(1, $one->bridges);
        self::assertSame(NoteStatus::Helpful, $one->status);

        $three = new CrossAgreementRule(minRatings: 4, pairs: 3, maxRaters: 6)->decide('n', $this->ratings->ofNote('n'), $this->ratings);
        self::assertSame([NoteStatus::NeedsMoreRatings, DecisionReason::NoCrossAgreement, 2], [$three->status, $three->reason, \count($three->bridges)]);
    }

    public function testOnlyTheEarliestRatersAreLookedAt(): void
    {
        foreach (['ann', 'bob', 'eve', 'cid', 'dan'] as $i => $rater) {
            $this->rate('n', $rater, Verdict::Helpful, 10 + $i);
        }

        $decision = new CrossAgreementRule(pairs: 1, maxRaters: 2)->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([NoteStatus::NeedsMoreRatings, []], [$decision->status, $decision->bridges]);
        self::assertSame(NoteStatus::Helpful, new CrossAgreementRule(pairs: 1, maxRaters: 4)->decide('n', $this->ratings->ofNote('n'), $this->ratings)->status);
    }

    public function testAHistoryTooShortOrTooAgreeableIsNoEvidence(): void
    {
        foreach (['ann', 'bob', 'cid', 'dan', 'eve'] as $i => $rater) {
            $this->rate('n', $rater, Verdict::Helpful, 10 + $i);
        }

        $ratings = $this->ratings->ofNote('n');
        self::assertSame(NoteStatus::NeedsMoreRatings, new CrossAgreementRule(minShared: 4)->decide('n', $ratings, $this->ratings)->status);
        self::assertSame(NoteStatus::Helpful, new CrossAgreementRule(minShared: 3)->decide('n', $ratings, $this->ratings)->status);
        self::assertSame(NoteStatus::Helpful, new CrossAgreementRule(divergence: [1, 100])->decide('n', $ratings, $this->ratings)->status);
        // The note being decided does not count in the history: ann and cid agree on it, and still disagree before.
        self::assertSame([3, 0], [$this->ratings->between($this->ref('ann'), $this->ref('cid'), 'n')->shared, $this->ratings->between($this->ref('ann'), $this->ref('cid'), 'n')->agreed]);
    }

    public function testTheRuleIsChecked(): void
    {
        $cases = [
            [static fn (): CrossAgreementRule => new CrossAgreementRule(minRatings: 0), 'A rule asks for at least one rating, one pair and one note shared.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(pairs: 0), 'A rule asks for at least one rating, one pair and one note shared.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(minShared: 0), 'A rule asks for at least one rating, one pair and one note shared.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(share: [1, 2]), 'The share of the leading verdict is above one half and at most all the ratings, not 1/2.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(share: [4, 3]), 'The share of the leading verdict is above one half and at most all the ratings, not 4/3.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(share: [1, 0]), 'The share of the leading verdict is above one half and at most all the ratings, not 1/0.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(divergence: [0, 2]), 'The share of agreement of two raters who usually disagree is above 0 and at most 1, not 0/2.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(divergence: [3, 2]), 'The share of agreement of two raters who usually disagree is above 0 and at most 1, not 3/2.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(divergence: [1, 0]), 'The share of agreement of two raters who usually disagree is above 0 and at most 1, not 1/0.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(pairs: 3, maxRaters: 5), 'A rule looks at 6 to 200 raters, not 5.'],
            [static fn (): CrossAgreementRule => new CrossAgreementRule(maxRaters: 201), 'A rule looks at 4 to 200 raters, not 201.'],
        ];

        foreach ($cases as [$make, $message]) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame($message, $invalidArgumentException->getMessage());
            }
        }

        self::assertSame(200, new CrossAgreementRule(maxRaters: 200)->maxRaters);
        self::assertSame([1, 1], new CrossAgreementRule(share: [1, 1], pairs: 1, divergence: [1, 1], maxRaters: 2)->share);
        self::assertSame(200, CrossAgreementRule::MAX_RATERS);
    }

    public function testRatersArePairedWithOthersOnceEach(): void
    {
        $ratings = [];

        foreach (['a', 'b', 'c', 'd', 'e'] as $i => $rater) {
            $ratings[] = new Rating('n', $this->ref($rater), Verdict::Helpful, new DateTimeImmutable('@'.(1_800_000_000 + $i)));
        }

        $decision = new CrossAgreementRule(pairs: 2, maxRaters: 5)->decide('n', $ratings, new FixedHistory(PairHistory::of(5, 0)));

        self::assertSame([['user:a', 'user:b'], ['user:c', 'user:d']], array_map(static fn (Bridge $bridge): array => [$bridge->first->toString(), $bridge->second->toString()], $decision->bridges));
    }

    public function testOnlyTheRatersOfTheLeadingVerdictArePaired(): void
    {
        // ann, bob and eve rate helpful, cid not; ann and cid usually disagree, but cid does not share the verdict.
        foreach (['ann' => Verdict::Helpful, 'bob' => Verdict::Helpful, 'eve' => Verdict::Helpful, 'cid' => Verdict::NotHelpful] as $rater => $verdict) {
            $this->rate('n', $rater, $verdict, 10);
        }

        $decision = new CrossAgreementRule(minRatings: 4, pairs: 1)->decide('n', $this->ratings->ofNote('n'), $this->ratings);

        self::assertSame([NoteStatus::NeedsMoreRatings, DecisionReason::NoCrossAgreement, [], 4], [$decision->status, $decision->reason, $decision->bridges, $decision->ratings()]);
        self::assertSame([3, 1], [$decision->helpful, $decision->notHelpful]);
    }

    public function testAgreeingOnMoreThanTheDivergenceIsNoEvidence(): void
    {
        $ratings = [
            new Rating('n', $this->ref('a'), Verdict::Helpful, new DateTimeImmutable('@1800000000')),
            new Rating('n', $this->ref('b'), Verdict::Helpful, new DateTimeImmutable('@1800000001')),
        ];
        $rule = new CrossAgreementRule(minRatings: 2, pairs: 1, divergence: [1, 2], maxRaters: 2);

        self::assertSame(DecisionReason::NoCrossAgreement, $rule->decide('n', $ratings, new FixedHistory(PairHistory::of(4, 3)))->reason);
        self::assertSame(DecisionReason::CrossAgreement, $rule->decide('n', $ratings, new FixedHistory(PairHistory::of(4, 1)))->reason);
    }

    public function testADecisionWithoutVerdictIsWrittenWithNone(): void
    {
        $decision = new CrossAgreementRule()->decide('n', [], $this->ratings);

        self::assertNull($decision->toArray()['verdict']);
        self::assertNull($decision->toPublicArray()['verdict']);
        self::assertSame([[], 'too_few_ratings'], [$decision->toPublicArray()['bridges'], $decision->toPublicArray()['reason']]);
    }

    public function testTheSmallestRuleIsAccepted(): void
    {
        $rule = new CrossAgreementRule(minRatings: 1, pairs: 1, minShared: 1, maxRaters: 2);

        self::assertSame([1, 1, 1, 2], [$rule->minRatings, $rule->pairs, $rule->minShared, $rule->maxRaters]);
    }

    private function rate(string $note, string $rater, Verdict $verdict, int $second): void
    {
        $this->ratings->save(new Rating($note, $this->ref($rater), $verdict, new DateTimeImmutable('@'.(1_800_000_000 + $second))));
    }

    private function ref(string $member): ResourceReference
    {
        return ResourceReference::of('user', $member);
    }
}
