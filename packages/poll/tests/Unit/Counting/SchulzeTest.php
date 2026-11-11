<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Counting\BallotGroups;
use Phil\Poll\Counting\Schulze;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Tests\Unit\Ballots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schulze::class)]
#[CoversClass(BallotGroups::class)]
final class SchulzeTest extends TestCase
{
    public function testTheMethod(): void
    {
        $method = new Schulze();

        self::assertSame('schulze', $method->name());
        self::assertTrue($method->supports(BallotKind::Ranked));
        self::assertFalse($method->supports(BallotKind::Single));
        self::assertFalse($method->supports(BallotKind::Multiple));
    }

    /**
     * The example of Markus Schulze's description of the method: 45 voters, five options, E first although A beats
     * every other option but E on its strongest paths.
     */
    public function testTheExampleOfTheMethod(): void
    {
        $result = new Schulze()->count(PollRules::ranked(['a', 'b', 'c', 'd', 'e']), Ballots::of([
            [5, ['a', 'c', 'b', 'e', 'd']],
            [5, ['a', 'd', 'e', 'c', 'b']],
            [8, ['b', 'e', 'd', 'a', 'c']],
            [3, ['c', 'a', 'b', 'e', 'd']],
            [7, ['c', 'a', 'e', 'b', 'd']],
            [2, ['c', 'b', 'a', 'd', 'e']],
            [7, ['d', 'c', 'e', 'b', 'a']],
            [8, ['e', 'b', 'a', 'd', 'c']],
        ]));

        self::assertSame(['schulze', 45, 0], [$result->method, $result->ballots, $result->abstentions]);
        self::assertSame([['e', 4, 1], ['a', 3, 2], ['c', 2, 3], ['b', 1, 4], ['d', 0, 5]], Ballots::standings($result));
        self::assertSame(['e'], $result->winners());
    }

    public function testACycleIsATie(): void
    {
        $result = new Schulze()->count(PollRules::ranked(['a', 'b', 'c']), Ballots::of([[1, ['a', 'b', 'c']], [1, ['b', 'c', 'a']], [1, ['c', 'a', 'b']]]));

        self::assertSame([['a', 0, 1], ['b', 0, 1], ['c', 0, 1]], Ballots::standings($result));
    }

    public function testAnUnrankedOptionComesAfterTheRankedOnes(): void
    {
        $result = new Schulze()->count(PollRules::ranked(['c', 'b', 'a'], 1, 3, true), Ballots::of([[2, ['a']], [1, ['b', 'c']], [4, []]]));

        self::assertSame([7, 4], [$result->ballots, $result->abstentions]);
        self::assertSame([['a', 2, 1], ['b', 1, 2], ['c', 0, 3]], Ballots::standings($result));
    }

    public function testAPathThroughAnotherOption(): void
    {
        // a beats b 5-4, b beats c 5-4, c beats a 6-3: the path a→b→c (5) loses to c→a (6), so c beats a; the path
        // b→c→a (5) ties a→b (5) and c→a→b (5) ties b→c (5), so neither b nor c beats the other, and b shares the
        // first place with c although it beats fewer options.
        $result = new Schulze()->count(PollRules::ranked(['a', 'b', 'c', 'd']), Ballots::of([
            [3, ['a', 'b', 'c', 'd']],
            [2, ['c', 'b', 'a', 'd']],
            [2, ['b', 'c', 'a', 'd']],
            [2, ['d', 'c', 'a', 'b']],
        ]));

        self::assertSame([['c', 2, 1], ['b', 1, 1], ['a', 1, 3], ['d', 0, 4]], Ballots::standings($result));
        self::assertSame(['c', 'b'], $result->winners());
    }

    public function testAPairwiseTieIsNoPath(): void
    {
        // a and b tie 3-3 and give no path; a beats c 4-2, while b and c tie 2-2.
        $result = new Schulze()->count(PollRules::ranked(['a', 'b', 'c']), Ballots::of([[1, ['b', 'a', 'c']], [1, ['c', 'a', 'b']], [1, ['b', 'a']], [2, ['a']], [1, ['c', 'b']]]));

        self::assertSame([['a', 1, 1], ['b', 0, 1], ['c', 0, 3]], Ballots::standings($result));
    }

    public function testOnlyRankedBallotsAreCounted(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The Schulze method counts ranked ballots only.'));

        new Schulze()->count(PollRules::single(['a', 'b']), []);
    }

    public function testAChoiceGivenTwiceIsRefused(): void
    {
        $this->expectException(InvalidBallot::class);

        new Schulze()->count(PollRules::ranked(['a', 'b']), [Ballot::of(['a', 'a'])]);
    }

    public function testIdenticalRankingsAreGrouped(): void
    {
        $groups = BallotGroups::read(PollRules::ranked(['a', 'b', 'c'], 1, 3, true), Ballots::of([[2, ['b', 'a']], [1, ['c']], [1, []], [1, ['b', 'a']], [1, ['a', 'b']]]));

        self::assertSame([[1, 0], [2], [0, 1]], $groups->rankings);
        self::assertSame([3, 1, 1], $groups->weights);
        self::assertSame([6, 1], [$groups->ballots, $groups->abstentions]);
    }
}
