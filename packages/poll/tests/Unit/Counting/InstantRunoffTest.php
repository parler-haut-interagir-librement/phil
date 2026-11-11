<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use Phil\Poll\Round;
use Phil\Poll\Tally;
use Phil\Poll\Tests\Unit\Ballots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstantRunoff::class)]
final class InstantRunoffTest extends TestCase
{
    public function testTheMethod(): void
    {
        $method = new InstantRunoff();

        self::assertSame('instant_runoff', $method->name());
        self::assertTrue($method->supports(BallotKind::Ranked));
        self::assertFalse($method->supports(BallotKind::Single));
        self::assertFalse($method->supports(BallotKind::Multiple));
    }

    public function testAMajorityWinsTheFirstRound(): void
    {
        $result = new InstantRunoff()->count(PollRules::ranked(['a', 'b', 'c'], 1, 3, true), Ballots::of([[3, ['b']], [2, ['a', 'b']], [1, []]]));

        self::assertSame(['instant_runoff', 6, 1], [$result->method, $result->ballots, $result->abstentions]);
        self::assertSame([['b', 3, 1], ['a', 2, 2], ['c', 0, 3]], Ballots::standings($result));
        self::assertSame([[['a', 2], ['b', 3], ['c', 0]], 0, []], $this->round($result, 0));
        self::assertCount(1, $result->rounds);
    }

    public function testHalfTheBallotsIsNoMajority(): void
    {
        $result = new InstantRunoff()->count(PollRules::ranked(['a', 'b', 'c']), Ballots::of([[2, ['a']], [1, ['b']], [1, ['c', 'b']]]));

        self::assertSame([['a', 2, 1], ['b', 2, 1]], \array_slice(Ballots::standings($result), 0, 2));
        self::assertSame([['c', 1, 3]], \array_slice(Ballots::standings($result), 2));
        self::assertSame([[['a', 2], ['b', 1], ['c', 1]], 0, ['c']], $this->round($result, 0));
        self::assertSame([[['a', 2], ['b', 2]], 0, []], $this->round($result, 1));
        self::assertSame(['a', 'b'], $result->winners());
    }

    public function testBallotsGoToTheirNextChoice(): void
    {
        $result = new InstantRunoff()->count(PollRules::ranked(['a', 'c', 'b', 'd']), Ballots::of([[4, ['a', 'b']], [3, ['b', 'c']], [2, ['c', 'b']], [1, ['d', 'c']]]));

        self::assertSame([[['a', 4], ['c', 2], ['b', 3], ['d', 1]], 0, ['d']], $this->round($result, 0));
        // c and b are tied; c held fewer ballots in the round before, although b is declared last.
        self::assertSame([[['a', 4], ['c', 3], ['b', 3]], 0, ['c']], $this->round($result, 1));
        self::assertSame([[['a', 4], ['b', 5]], 1, []], $this->round($result, 2));
        self::assertSame([['b', 5, 1], ['a', 4, 2], ['c', 3, 3], ['d', 1, 4]], Ballots::standings($result));
        self::assertSame(['b'], $result->winners());
    }

    public function testTheLatestRoundWhereTheTiedOptionsDifferDecides(): void
    {
        $result = new InstantRunoff()->count(PollRules::ranked(['a', 'x', 'y', 'p', 'q']), Ballots::of([
            [6, ['a']],
            [4, ['x']],
            [3, ['y']],
            [2, ['p', 'y']],
            [1, ['q', 'x']],
            [2, ['q']],
        ]));

        self::assertSame([[['a', 6], ['x', 4], ['y', 3], ['p', 2], ['q', 3]], 0, ['p']], $this->round($result, 0));
        self::assertSame([[['a', 6], ['x', 4], ['y', 5], ['q', 3]], 0, ['q']], $this->round($result, 1));
        // x and y are tied; x held fewer in the round before, more in the first one.
        self::assertSame([[['a', 6], ['x', 5], ['y', 5]], 2, ['x']], $this->round($result, 2));
        self::assertSame([[['a', 6], ['y', 5]], 7, []], $this->round($result, 3));
        self::assertSame([['a', 6, 1], ['y', 5, 2], ['x', 5, 3], ['q', 3, 4], ['p', 2, 5]], Ballots::standings($result));
    }

    public function testATieThroughEveryRoundEliminatesTheOptionDeclaredLast(): void
    {
        $result = new InstantRunoff()->count(PollRules::ranked(['a', 'b', 'c', 'd', 'e']), Ballots::of([[3, ['a']], [2, ['b']], [2, ['c']], [1, ['d']], [1, ['e']]]));

        self::assertSame(['e'], $result->rounds[0]->eliminated);
        self::assertSame(['d'], $result->rounds[1]->eliminated);
        self::assertSame(['c'], $result->rounds[2]->eliminated);
        self::assertSame([[['a', 3], ['b', 2]], 4, []], $this->round($result, 3));
        self::assertSame([['a', 3, 1], ['b', 2, 2], ['c', 2, 3], ['d', 1, 4], ['e', 1, 5]], Ballots::standings($result));
    }

    public function testNoBallotIsATieOfEveryOption(): void
    {
        $result = new InstantRunoff()->count(PollRules::ranked(['a', 'b'], 1, 2, true), Ballots::of([[2, []]]));

        self::assertSame([['a', 0, 1], ['b', 0, 1]], Ballots::standings($result));
        self::assertSame([[['a', 0], ['b', 0]], 0, []], $this->round($result, 0));
        self::assertSame([], $result->winners());
    }

    public function testOnlyRankedBallotsAreCounted(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The instant runoff counts ranked ballots only.'));

        new InstantRunoff()->count(PollRules::single(['a', 'b']), []);
    }

    public function testAnUnknownChoiceIsRefused(): void
    {
        $this->expectException(InvalidBallot::class);

        new InstantRunoff()->count(PollRules::ranked(['a', 'b']), [Ballot::of(['a', 'z'])]);
    }

    /**
     * @return array{list<array{string, int}>, int, list<string>}
     */
    private function round(Result $result, int $round): array
    {
        $recorded = $result->rounds[$round];
        self::assertInstanceOf(Round::class, $recorded);

        return [array_map(static fn (Tally $tally): array => [$tally->option, $tally->votes], $recorded->tallies), $recorded->exhausted, $recorded->eliminated];
    }
}
