<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Domain\Counting;

use InvalidArgumentException;
use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Standing;
use Phil\PollBundle\Domain\Counting\Billionths;
use Phil\PollBundle\Domain\Counting\CandidateTally;
use Phil\PollBundle\Domain\Counting\Election;
use Phil\PollBundle\Domain\Counting\ElectionRound;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Status\CandidateStatus;
use Phil\PollBundle\Domain\Status\TieBreak;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeekStv::class)]
#[CoversClass(Election::class)]
#[CoversClass(ElectionRound::class)]
#[CoversClass(CandidateTally::class)]
final class MeekStvTest extends TestCase
{
    /**
     * The food election of the article "Single transferable vote" of the English Wikipedia: twenty voters, three
     * seats; Chocolate, Oranges and Strawberries are elected.
     */
    public function testThePublishedFoodElection(): void
    {
        $election = new MeekStv()->elect(
            PollRules::ranked(['orange', 'pear', 'chocolate', 'strawberry', 'bonbon', 'hamburger']),
            $this->ballots([4, ['orange']], [2, ['pear', 'orange']], [8, ['chocolate', 'strawberry']], [4, ['chocolate', 'bonbon']], [1, ['strawberry']], [1, ['hamburger']]),
            3,
        );

        self::assertSame(['chocolate', 'strawberry', 'orange'], $election->elected);
        self::assertSame([20, 0, 3, 'meek_stv'], [$election->ballots, $election->abstentions, $election->seats, $election->method]);
        self::assertCount(3, $election->rounds);
        self::assertSame([
            'number' => 1,
            'iterations' => 20,
            'quota' => '4.714288797',
            'tallies' => [
                ['option' => 'orange', 'votes' => '4.000000000', 'keep_factor' => '1.000000000', 'status' => 'hopeful'],
                ['option' => 'pear', 'votes' => '2.000000000', 'keep_factor' => '1.000000000', 'status' => 'hopeful'],
                ['option' => 'chocolate', 'votes' => '4.714291488', 'keep_factor' => '0.392857624', 'status' => 'elected'],
                ['option' => 'strawberry', 'votes' => '4.714294194', 'keep_factor' => '0.804880026', 'status' => 'elected'],
                ['option' => 'bonbon', 'votes' => '2.428569504', 'keep_factor' => '1.000000000', 'status' => 'hopeful'],
                ['option' => 'hamburger', 'votes' => '1.000000000', 'keep_factor' => '1.000000000', 'status' => 'excluded'],
            ],
            'exhausted' => '1.142844814',
            'surplus' => '0.000008088',
            'elected' => ['chocolate', 'strawberry'],
            'excluded' => ['hamburger'],
            'tie_break' => null,
        ], $election->rounds[0]->toArray());
        self::assertSame([18, '4.285717517', '2.857129936', '0.000008518', [], ['pear']], [
            $election->rounds[1]->iterations,
            $election->rounds[1]->quota->toString(),
            $election->rounds[1]->exhausted->toString(),
            $election->rounds[1]->surplus->toString(),
            $election->rounds[1]->elected,
            $election->rounds[1]->excluded,
        ]);
        self::assertNull($election->rounds[1]->tallyOf('hamburger'));
        self::assertSame(['orange'], $election->rounds[2]->elected);
        self::assertSame(['bonbon'], $election->rounds[2]->excluded);
        self::assertSame('6.000000000', $election->rounds[2]->tallyOf('orange')?->votes->toString());
        self::assertSame(CandidateStatus::Defeated, $election->rounds[2]->tallyOf('bonbon')?->status);
        self::assertTrue($election->rounds[2]->isElected('orange'));
        self::assertFalse($election->rounds[2]->isElected('bonbon'));
        self::assertFalse($election->rounds[2]->isElected('pear'));

        $result = $election->toResult();
        self::assertSame(['chocolate', 'strawberry', 'orange'], $result->winners());
        self::assertSame(
            [['chocolate', 4_285_720_464, 1], ['strawberry', 4_285_723_088, 1], ['orange', 6_000_000_000, 1], ['bonbon', 2_571_426_512, 4], ['pear', 2_000_000_000, 5], ['hamburger', 1_000_000_000, 6]],
            array_map(static fn (Standing $standing): array => [$standing->option, $standing->score, $standing->place], $result->standings),
        );
        self::assertSame([], $result->rounds);
    }

    public function testTheVotesAndTheExhaustedValueAlwaysAddUpToTheBallots(): void
    {
        $options = ['a', 'b', 'c', 'd', 'e', 'f'];
        $ballots = [];

        for ($voter = 0; $voter < 97; ++$voter) {
            $ranking = [];

            for ($rank = 0, $depth = 1 + $voter % 6; $rank < $depth; ++$rank) {
                $ranking[] = $options[($voter * 5 + $rank * ($voter % 4 + 1)) % 6];
            }

            $ballots[] = Ballot::of(array_values(array_unique($ranking)));
        }

        $election = new MeekStv()->elect(PollRules::ranked($options), $ballots, 3);

        self::assertCount(3, $election->elected);

        foreach ($election->rounds as $round) {
            $votes = array_sum(array_map(static fn (CandidateTally $tally): int => $tally->votes->numerator, $round->tallies));
            self::assertSame(97 * Billionths::SCALE, $votes + $round->exhausted->numerator, 'round '.$round->number);
        }
    }

    public function testAVoteReachingAnElectedOptionPassesOnWhatItDoesNotKeep(): void
    {
        $election = new MeekStv()->elect(
            PollRules::ranked(['ann', 'bob', 'cat', 'dan']),
            $this->ballots([9, ['ann', 'bob', 'cat']], [6, ['bob', 'ann', 'dan']], [4, ['cat']], [5, ['dan']]),
            2,
        );

        self::assertSame(['ann', 'bob'], $election->elected);
        self::assertSame(['8.000000001', '0.888888889', '6.999999999', 2], [
            $election->rounds[0]->quota->toString(),
            $election->rounds[0]->tallyOf('ann')?->keepFactor->toString(),
            $election->rounds[0]->tallyOf('bob')?->votes->toString(),
            $election->rounds[0]->iterations,
        ]);
        self::assertSame(['cat'], $election->rounds[0]->excluded);
        self::assertSame(['6.666666667', '1.666666666', ['bob'], ['dan']], [
            $election->rounds[1]->quota->toString(),
            $election->rounds[1]->surplus->toString(),
            $election->rounds[1]->elected,
            $election->rounds[1]->excluded,
        ]);
    }

    public function testATieNeverBrokenBeforeExcludesTheOptionDeclaredLast(): void
    {
        $election = new MeekStv()->elect(PollRules::ranked(['a', 'b', 'c']), $this->ballots([2, ['a']], [1, ['b']], [1, ['c']]), 1);

        self::assertSame(['c'], $election->rounds[0]->excluded);
        self::assertSame(TieBreak::DeclaredOrder, $election->rounds[0]->tieBreak);
        self::assertSame('declared_order', $election->rounds[0]->toArray()['tie_break']);
        self::assertSame(['2.000000001', '0.000000000'], [$election->rounds[0]->quota->toString(), $election->rounds[0]->surplus->toString()]);
        self::assertSame(['1.500000001', '1.000000000', '0.499999999'], [$election->rounds[1]->quota->toString(), $election->rounds[1]->exhausted->toString(), $election->rounds[1]->surplus->toString()]);
        self::assertSame([['a'], ['b'], null], [$election->rounds[1]->elected, $election->rounds[1]->excluded, $election->rounds[1]->tieBreak]);
        self::assertSame(['a'], $election->elected);
    }

    public function testATieIsBrokenByTheRoundsBefore(): void
    {
        $election = new MeekStv()->elect(PollRules::ranked(['a', 'b', 'c', 'd']), $this->ballots([4, ['a']], [2, ['b']], [3, ['c']], [1, ['d', 'b']]), 1);

        self::assertSame([['d'], null], [$election->rounds[0]->excluded, $election->rounds[0]->tieBreak]);
        self::assertSame(['3.000000000', '3.000000000'], [$election->rounds[1]->tallyOf('b')?->votes->toString(), $election->rounds[1]->tallyOf('c')?->votes->toString()]);
        self::assertSame([['b'], TieBreak::EarlierRounds], [$election->rounds[1]->excluded, $election->rounds[1]->tieBreak]);
        self::assertSame(['a'], $election->elected);
        self::assertSame([['a', 1], ['c', 2], ['b', 3], ['d', 4]], array_map(static fn (Standing $standing): array => [$standing->option, $standing->place], $election->toResult()->standings));
    }

    public function testTheHopefulOptionsLeftAreElectedWhenTheyFitTheSeats(): void
    {
        // c reaches the quota; of a, b and d, tied, d is excluded; a and b then fit the two seats left, tied, the one
        // declared first first.
        $election = new MeekStv()->elect(PollRules::ranked(['a', 'b', 'c', 'd']), $this->ballots([1, ['b']], [2, ['c']], [1, ['d']], [1, ['a']]), 3);

        self::assertSame(['c', 'a', 'b'], $election->elected);
        self::assertSame([['c'], ['d'], TieBreak::DeclaredOrder], [$election->rounds[0]->elected, $election->rounds[0]->excluded, $election->rounds[0]->tieBreak]);
        $last = $election->rounds[1];
        self::assertSame([['a', 'b'], []], [$last->elected, $last->excluded]);
        self::assertSame(CandidateStatus::Elected, $last->tallyOf('b')?->status);
        self::assertSame([['c', 1], ['a', 1], ['b', 1], ['d', 4]], array_map(static fn (Standing $standing): array => [$standing->option, $standing->place], $election->toResult()->standings));
    }

    public function testTheSeatsFilledLeaveTheOthersDefeated(): void
    {
        $election = new MeekStv()->elect(PollRules::ranked(['a', 'b', 'c']), $this->ballots([5, ['a']], [2, ['b']], [1, ['c']]), 2);

        self::assertSame(['a', 'b'], $election->elected);
        self::assertCount(1, $election->rounds);
        self::assertSame([['a', 'b'], ['c']], [$election->rounds[0]->elected, $election->rounds[0]->excluded]);
        self::assertSame(CandidateStatus::Defeated, $election->rounds[0]->tallyOf('c')?->status);
    }

    public function testOneSeatIsTheCountOfThePoll(): void
    {
        $method = new MeekStv();
        $result = $method->count(PollRules::ranked(['tea', 'coffee', 'cocoa'], abstention: true), $this->ballots([2, ['coffee', 'tea']], [2, ['tea']], [1, ['cocoa', 'tea']], [1, []]));

        self::assertSame('meek_stv', $method->name());
        self::assertSame(MeekStv::NAME, $result->method);
        self::assertSame(['tea'], $result->winners());
        self::assertSame([6, 1], [$result->ballots, $result->abstentions]);
        self::assertTrue($method->supports(BallotKind::Ranked));
        self::assertFalse($method->supports(BallotKind::Single));
        self::assertFalse($method->supports(BallotKind::Multiple));
    }

    public function testNoBallotElectsNobody(): void
    {
        $rules = PollRules::ranked(['a', 'b', 'c'], abstention: true);

        foreach ([[], [Ballot::of([]), Ballot::of([])]] as $ballots) {
            $election = new MeekStv()->elect($rules, $ballots, 2);

            self::assertSame([[], []], [$election->elected, $election->rounds]);
            self::assertSame([\count($ballots), \count($ballots)], [$election->ballots, $election->abstentions]);
            self::assertSame([], $election->toResult()->winners());
            self::assertSame([['a', 0, 1], ['b', 0, 1], ['c', 0, 1]], array_map(static fn (Standing $standing): array => [$standing->option, $standing->score, $standing->place], $election->toResult()->standings));
        }
    }

    public function testTheSameBallotsGiveTheSameRounds(): void
    {
        $rules = PollRules::ranked(['orange', 'pear', 'chocolate', 'strawberry', 'bonbon', 'hamburger']);
        $ballots = $this->ballots([4, ['orange']], [2, ['pear', 'orange']], [8, ['chocolate', 'strawberry']], [4, ['chocolate', 'bonbon']], [1, ['strawberry']], [1, ['hamburger']]);
        $published = new MeekStv()->elect($rules, $ballots, 3)->toArray();

        self::assertSame($published, new MeekStv()->elect($rules, array_reverse($ballots), 3)->toArray());
        self::assertSame(['method' => 'meek_stv', 'seats' => 3, 'ballots' => 20, 'abstentions' => 0, 'elected' => ['chocolate', 'strawberry', 'orange']], \array_slice($published, 0, 5));
        self::assertCount(3, $published['rounds']);
    }

    public function testTheRulesAndTheSeatsAreChecked(): void
    {
        $method = new MeekStv();
        $cases = [
            'single' => [PollRules::single(['a', 'b']), 1, 'The single transferable vote counts ranked ballots only.'],
            'multiple' => [PollRules::multiple(['a', 'b']), 1, 'The single transferable vote counts ranked ballots only.'],
            'no seat' => [PollRules::ranked(['a', 'b', 'c']), 0, 'A single transferable vote fills 1 to 2 seats among 3 options, not 0.'],
            'every option' => [PollRules::ranked(['a', 'b', 'c']), 3, 'A single transferable vote fills 1 to 2 seats among 3 options, not 3.'],
        ];

        foreach ($cases as $case => [$rules, $seats, $message]) {
            try {
                $method->elect($rules, [], $seats);
                self::fail($case);
            } catch (InvalidPoll $invalidPoll) {
                self::assertSame($message, $invalidPoll->getMessage(), $case);
            }
        }

        self::assertSame(['a', 'b'], $method->elect(PollRules::ranked(['a', 'b', 'c']), [Ballot::of(['a'])], 2)->elected);
    }

    public function testABallotIsCheckedAgain(): void
    {
        foreach ([[['x'], '"x" is not an option of this poll.'], [['a', 'b', 'a'], 'The option "a" is chosen twice.']] as [$choices, $message]) {
            try {
                new MeekStv()->elect(PollRules::ranked(['a', 'b', 'c']), [Ballot::of(['b']), Ballot::of($choices)], 1);
                self::fail($message);
            } catch (InvalidBallot $invalidBallot) {
                self::assertSame($message, $invalidBallot->getMessage());
            }
        }
    }

    public function testTheBoundsOfACount(): void
    {
        self::assertSame([10_000, 1_000, 1_000_000_000], [MeekStv::SETTLED, MeekStv::MAX_PASSES, MeekStv::MAX_BALLOTS]);
        $rules = PollRules::ranked(['a', 'b', 'c']);
        $ballots = [Ballot::of(['a']), Ballot::of(['b']), Ballot::of(['a'])];

        self::assertSame(['a'], new MeekStv(3)->elect($rules, $ballots, 1)->elected);
        self::assertSame(['a'], new MeekStv(MeekStv::MAX_BALLOTS)->elect($rules, $ballots, 1)->elected);

        try {
            new MeekStv(2)->elect($rules, $ballots, 1);
            self::fail('Counted.');
        } catch (InvalidPoll $invalidPoll) {
            self::assertSame('A single transferable vote counts at most 2 ballots.', $invalidPoll->getMessage());
        }

        self::assertSame(['a'], new MeekStv(1)->elect($rules, [Ballot::of(['a'])], 1)->elected);

        foreach ([0, MeekStv::MAX_BALLOTS + 1] as $bound) {
            try {
                new MeekStv($bound);
                self::fail((string) $bound);
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('A single transferable vote counts at most 1 to 1000000000 ballots, not %d.', $bound), $invalidArgumentException->getMessage());
            }
        }
    }

    public function testARoundWhoseExcludedOptionIsNotTalliedIsNotTheLast(): void
    {
        $round = new ElectionRound(1, 1, Billionths::of(1), [new CandidateTally('a', Billionths::of(1), Billionths::whole(1), CandidateStatus::Hopeful)], Billionths::of(0), Billionths::of(0), [], ['gone']);

        self::assertFalse($round->isLast());
        self::assertNull($round->tallyOf('gone'));
    }

    public function testAProductIsDividedWithoutLeavingAnInteger(): void
    {
        self::assertSame(0, MeekStv::mulDivUp(0, 5, 7));
        self::assertSame(1, MeekStv::mulDivUp(1, 1, 7));
        self::assertSame(3, MeekStv::mulDivUp(2, 10, 7));
        self::assertSame(20, MeekStv::mulDivUp(2, 70, 7));
        self::assertSame(1_000_000_000, MeekStv::mulDivUp(1_000_000_000, 4_000_000_000_000_000_000, 4_000_000_000_000_000_000));
        self::assertSame(888_888_889, MeekStv::mulDivUp(1_000_000_000, 8_000_000_001, 9_000_000_000));
        self::assertSame(500_000_001, MeekStv::mulDivUp(1_000_000_000, 2_000_000_000_000_000_001, 4_000_000_000_000_000_000));
        self::assertSame(1_073_741_823, MeekStv::mulDivUp(1_073_741_823, 3_999_999_999_999_999_999, 3_999_999_999_999_999_999));
        self::assertSame(536_870_912, MeekStv::mulDivUp(536_870_912, 1, 1));
        self::assertSame(2_000_000_000, MeekStv::mulDivUp(1_000_000_000, 6, 3));
    }

    /**
     * @param array{int, list<string>} ...$groups
     *
     * @return list<Ballot>
     */
    private function ballots(array ...$groups): array
    {
        $ballots = [];

        foreach ($groups as [$times, $choices]) {
            for ($i = 0; $i < $times; ++$i) {
                $ballots[] = Ballot::of($choices);
            }
        }

        return $ballots;
    }
}
